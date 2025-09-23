<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentXLabManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\MetricsPlatform\MetricsClient;

/**
 * @coversDefaultClass \GrowthExperiments\ExperimentXLabManager
 */
class ExperimentXLabManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantNoExperiment() {
		$user = new UserIdentityValue( 0, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentXLabManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'MetricsPlatformEnableExperiments' => false,
					'MetricsPlatformEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			...$this->getXLabDependencies(),
			new HashConfig( [ 'MetricsPlatformExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentXLabManager {
			// No valid experiments, fallback to configured default, same as for ExperimentUserManager
			public const VALID_EXPERIMENTS = [];
		};

		$this->assertEquals( 'Foo', $sut->getVariant( $user ) );
	}

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantNoEnrolledExperiments() {
		$user1 = new UserIdentityValue( 1, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentXLabManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'MetricsPlatformEnableExperiments' => false,
					'MetricsPlatformEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			...$this->getXLabDependencies(
				[
					'experiment-1' => null,
					'experiment-2' => null,
				]
			),
			new HashConfig( [ 'MetricsPlatformExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentXLabManager {
			public const VALID_EXPERIMENTS = [
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertEquals( "Foo", $sut->getVariant( $user1 ) );
	}

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantWithUserInExperiment() {
		$user1 = new UserIdentityValue( 1, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentXLabManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'MetricsPlatformEnableExperiments' => false,
					'MetricsPlatformEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			...$this->getXLabDependencies(
				[
					'experiment-3' => null,
					'experiment-2' => 'some-group',
					'experiment-1' => null,
				]
			),
			new HashConfig( [ 'MetricsPlatformExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentXLabManager {
			public const VALID_EXPERIMENTS = [
				'experiment-3',
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertEquals(
			"experiment-2_some-group",
			$sut->getVariant( $user1 )
		);
	}

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantWithUserInMultipleExperiment() {
		$user1 = new UserIdentityValue( 1, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentXLabManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'MetricsPlatformEnableExperiments' => false,
					'MetricsPlatformEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			...$this->getXLabDependencies(
				[
					'experiment-3' => 'another-group',
					'experiment-2' => 'some-group',
					'experiment-1' => null,
				]
			),
			new HashConfig( [ 'MetricsPlatformExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentXLabManager {
			public const VALID_EXPERIMENTS = [
				'experiment-3',
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertEquals(
			"experiment-3_another-group",
			$sut->getVariant( $user1 )
		);
	}

	/**
	 * @covers ::getValidVariants
	 */
	public function testGetValidVariants() {
		$options = [
			new ServiceOptions(

				ExperimentXLabManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'MetricsPlatformEnableExperiments' => false,
					'MetricsPlatformEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			...$this->getXLabDependencies( [
				'experiment-1' => null,
				'experiment-2' => null,
			] ),
			new HashConfig( [ 'MetricsPlatformExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentXLabManager {
			public const VALID_EXPERIMENTS = [
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertArrayContains(
			[
				'experiment-2_' . ExperimentXLabManager::VARIANT_CONTROL,
				'experiment-2_' . ExperimentXLabManager::VARIANT_TREATMENT,
				'experiment-1_' . ExperimentXLabManager::VARIANT_CONTROL,
				'experiment-1_' . ExperimentXLabManager::VARIANT_TREATMENT,
			],
			$sut->getValidVariants()
		);
	}

	private function getXLabDependencies( ?array $assignments = [] ): array {
		if ( !class_exists( 'MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager' ) ) {
			$this->markTestSkipped( 'MetricsPlatform\XLab\ExperimentManager is not available.' );
		}
		$experimentManager = $this->createMock( ExperimentManager::class );
		$configsFetcher = $this->createMock( InstrumentConfigsFetcher::class );
		$enrollmentAuthority = $this->createMock( EnrollmentAuthority::class );
		$metricsClientMock = $this->createMock( MetricsClient::class );
		$experimentManager->method( 'getExperiment' )
			->willReturnOnConsecutiveCalls(
				...array_map( static function ( $experimentName ) use ( $metricsClientMock, $assignments ) {
					return new Experiment(
						$metricsClientMock,
						[
							'enrolled' => $experimentName,
							'assigned' => $assignments[ $experimentName ] ?? null,
							'subject_ids' => 'A subject id',
							'sampling_unit' => 'A sampling unit',
							'coordinator' => 'Growth unit test',
						]
					);
				}, array_keys( $assignments ) ),
			);
		return [
			$configsFetcher,
			$enrollmentAuthority,
			$experimentManager,
		];
	}
}
