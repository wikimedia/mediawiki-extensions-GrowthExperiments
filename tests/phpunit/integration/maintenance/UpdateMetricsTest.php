<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Maintenance\UpdateMetrics;
use GrowthExperiments\PeriodicMetrics\AutoAssignedMentorsMetric;
use GrowthExperiments\PeriodicMetrics\IMetric;
use GrowthExperiments\PeriodicMetrics\InactiveMentorsMetric;
use GrowthExperiments\PeriodicMetrics\MetricsFactory;
use GrowthExperiments\PeriodicMetrics\NewcomersByMentorMetric;
use InvalidArgumentException;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\NullLogger;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Stats\StatsUtils;
use Wikimedia\Stats\UnitTestingHelper;

/**
 * @covers \GrowthExperiments\Maintenance\UpdateMetrics
 * @group Database
 */
class UpdateMetricsTest extends MaintenanceBaseTestCase {

	private UnitTestingHelper $statsHelper;

	protected function getMaintenanceClass(): string {
		return UpdateMetrics::class;
	}

	protected function setUp(): void {
		parent::setUp();
		$this->statsHelper = StatsFactory::newUnitTestingHelper();
		$this->setService( 'StatsFactory', $this->statsHelper->getStatsFactory() );
		$this->statsHelper->withComponent( 'GrowthExperiments' );
	}

	private function mockMetric( string $statsLibKey, int $value ): IMetric {
		$metric = $this->createMock( IMetric::class );
		$metric->method( 'getStatsLibKey' )->willReturn( $statsLibKey );
		$metric->method( 'calculate' )->willReturn( $value );
		return $metric;
	}

	/**
	 * @param array<class-string<IMetric>,IMetric|InvalidArgumentException> $metrics
	 */
	private function setMetricsFactory( array $metrics ): void {
		$metricsFactory = $this->createMock( MetricsFactory::class );
		$metricsFactory->method( 'newMetric' )->willReturnCallback(
			static function ( string $className ) use ( $metrics ): IMetric {
				if ( $metrics[$className] instanceof InvalidArgumentException ) {
					throw $metrics[$className];
				}
				return $metrics[$className];
			}
		);
		$this->setService( 'GrowthExperimentsMetricsFactory', $metricsFactory );
	}

	private function assertGauge( string $statsLibKey, float $expected ): void {
		$wiki = StatsUtils::normalizeString( WikiMap::getCurrentWikiId() );
		$this->assertSame( 1, $this->statsHelper->count( "$statsLibKey{wiki=\"$wiki\"}" ) );
		$this->assertSame( $expected, $this->statsHelper->last( "$statsLibKey{wiki=\"$wiki\"}" ) );
	}

	public function testExecuteSetsGaugeForEachMetric(): void {
		$this->setMetricsFactory( [
			AutoAssignedMentorsMetric::class => $this->mockMetric( 'mentorship_automentors', 5 ),
			InactiveMentorsMetric::class => $this->mockMetric( 'mentorship_inactive_mentors', 2 ),
			NewcomersByMentorMetric::class => $this->mockMetric( 'mentorship_newcomers_by_mentor', 42 ),
		] );

		$this->expectOutputString( '' );
		$this->maintenance->execute();

		$this->assertGauge( 'mentorship_automentors', 5.0 );
		$this->assertGauge( 'mentorship_inactive_mentors', 2.0 );
		$this->assertGauge( 'mentorship_newcomers_by_mentor', 42.0 );
	}

	public function testExecuteVerbose(): void {
		$this->setMetricsFactory( [
			AutoAssignedMentorsMetric::class => $this->mockMetric( 'mentorship_automentors', 5 ),
			InactiveMentorsMetric::class => $this->mockMetric( 'mentorship_inactive_mentors', 2 ),
			NewcomersByMentorMetric::class => $this->mockMetric( 'mentorship_newcomers_by_mentor', 42 ),
		] );

		$this->maintenance->setOption( 'verbose', true );
		$this->expectOutputString(
			AutoAssignedMentorsMetric::class . ' is 5.' . PHP_EOL .
			InactiveMentorsMetric::class . ' is 2.' . PHP_EOL .
			NewcomersByMentorMetric::class . ' is 42.' . PHP_EOL
		);
		$this->maintenance->execute();
	}

	public function testExecuteSkipsMetricThatFailsToConstruct(): void {
		$this->setMetricsFactory( [
			AutoAssignedMentorsMetric::class => $this->mockMetric( 'mentorship_automentors', 5 ),
			InactiveMentorsMetric::class => new InvalidArgumentException( 'Unsupported metric class name' ),
			NewcomersByMentorMetric::class => $this->mockMetric( 'mentorship_newcomers_by_mentor', 42 ),
		] );

		$this->setLogger( 'exception', new NullLogger() );
		$this->expectOutputString(
			'ERROR: Metric "' . InactiveMentorsMetric::class . '" failed to be constructed' . "\n"
		);
		$this->maintenance->execute();

		$this->assertGauge( 'mentorship_automentors', 5.0 );
		$this->assertGauge( 'mentorship_newcomers_by_mentor', 42.0 );
		$this->assertCount( 2, $this->statsHelper->getAllFormatted() );
	}

	public function testExecuteWithRealMetrics(): void {
		$this->expectOutputString( '' );
		$this->maintenance->execute();

		$this->assertGauge( 'mentorship_automentors', 0.0 );
		$this->assertGauge( 'mentorship_inactive_mentors', 0.0 );
		$this->assertGauge( 'mentorship_newcomers_by_mentor', 0.0 );
	}
}
