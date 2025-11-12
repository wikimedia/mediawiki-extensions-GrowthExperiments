<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialMentorDashboard;
use MediaWiki\SpecialPage\SpecialPage;
use SpecialPageTestBase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \GrowthExperiments\Specials\SpecialMentorDashboard
 */
class SpecialMentorDashboardTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMainCache( CACHE_NONE );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		return new SpecialMentorDashboard(
			$geServices->getMentorDashboardModuleRegistry(),
			$geServices->getMentorProvider(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getJobQueueGroupFactory()
		);
	}

	public function testIsMentor() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();

		$mentorUser = $this->getTestUser()->getUser();
		$this->assertStatusGood( $mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser,
			''
		) );
		$this->assertTrue( $mentorProvider->isMentor( $mentorUser ) );

		$geServices->getMenteeOverviewDataUpdater()->updateDataForMentor( $mentorUser );
		/** @var string $html */
		[ $html ] = $this->executeSpecialPage( '', null, null, $mentorUser );
		$this->assertNotEmpty( $html );
	}

	public function testNonMentor() {
		$user = $this->getTestUser()->getUser();

		[ , $response ] = $this->executeSpecialPage( '', null, null, $user );
		$this->assertEquals(
			SpecialPage::getTitleFor( 'EnrollAsMentor' )->getFullURL(),
			$response->getHeader( 'Location' )
		);
	}

	public function testMentorCanAccessPageWhenMenteeDataHasNotBeenUpdated() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();

		$mentorUser = $this->getTestUser()->getUser();
		$mentorWriter->addMentor( $mentorProvider->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser, 'adding a new mentor' );

		[ $html ] = $this->executeSpecialPage( '', null, null, $mentorUser );
		$this->assertStringContainsString( "growthexperiments-mentor-dashboard-mentee-overview-headline", $html );
	}

	public function testMentorSeesLastUpdateTimeOfMenteeData() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();

		$mentorUser = $this->getTestUser()->getUser();
		$mentorWriter->addMentor( $mentorProvider->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser, 'adding a new mentor' );
		$geServices->getMenteeOverviewDataUpdater()->updateDataForMentor( $mentorUser );
		// After update, set current time to 2 hours in the future so there is elapsed time between the update and "now"
		ConvertibleTimestamp::setFakeTime( time() + 3600 * 2 );

		[ $html ] = $this->executeSpecialPage( '', null, '', $mentorUser );
		$this->assertStringContainsString(
			'(growthexperiments-mentor-dashboard-mentee-overview-info-updated: (duration-hours: 2))', $html );
	}
}
