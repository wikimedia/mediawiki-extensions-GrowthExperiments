<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialMentorDashboard;
use MediaWiki\SpecialPage\SpecialPage;
use SpecialPageTestBase;

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
}
