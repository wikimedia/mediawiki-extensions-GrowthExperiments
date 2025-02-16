<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\PeriodicMetrics\AutoAssignedMentorsMetric;
use GrowthExperiments\PeriodicMetrics\InactiveMentorsMetric;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\PeriodicMetrics\MetricsFactory
 * @group Database
 */
class MetricsFactoryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\PeriodicMetrics\AutoAssignedMentorsMetric
	 * @covers \GrowthExperiments\PeriodicMetrics\InactiveMentorsMetric
	 */
	public function testMetrics() {
		$this->setMainCache( CACHE_NONE );
		ConvertibleTimestamp::setFakeTime( '20220101000000' );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorWriter = $geServices->getMentorWriter();
		$mentorProvider = $geServices->getMentorProvider();
		$metricsFactory = $geServices->getMetricsFactory();

		$userOne = $this->getMutableTestUser()->getUser();
		$mentorOne = $mentorProvider->newMentorFromUserIdentity( $userOne );
		$mentorOne->setWeight( Mentor::WEIGHT_NONE );

		$userTwo = $this->getMutableTestUser()->getUser();
		$mentorTwo = $mentorProvider->newMentorFromUserIdentity( $userTwo );
		$mentorTwo->setWeight( Mentor::WEIGHT_HIGH );

		$this->assertStatusOK(
			$mentorWriter->addMentor( $mentorOne, $mentorOne->getUserIdentity(), '' )
		);
		$this->assertStatusOK(
			$mentorWriter->addMentor( $mentorTwo, $mentorTwo->getUserIdentity(), '' )
		);

		ConvertibleTimestamp::setFakeTime( time() );
		$this->assertSame(
			1,
			$metricsFactory->newMetric( AutoAssignedMentorsMetric::class )->calculate()
		);
		$this->assertSame(
			1,
			$metricsFactory->newMetric( InactiveMentorsMetric::class )->calculate()
		);

		$this->editPage( 'Foo', 'foo', '', NS_MAIN, $userOne );
		$this->editPage( 'Foo', 'bar', '', NS_MAIN, $userTwo );

		$this->assertSame(
			1,
			$metricsFactory->newMetric( AutoAssignedMentorsMetric::class )->calculate()
		);
		$this->assertSame(
			0,
			$metricsFactory->newMetric( InactiveMentorsMetric::class )->calculate()
		);
	}
}
