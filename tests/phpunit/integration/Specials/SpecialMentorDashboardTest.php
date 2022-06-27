<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Specials\SpecialMentorDashboard;
use SpecialPage;
use SpecialPageTestBase;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialMentorDashboard
 */
class SpecialMentorDashboardTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgGEMentorProvider', MentorProvider::PROVIDER_STRUCTURED );
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

	/**
	 * @covers ::execute
	 */
	public function testIsMentor() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();

		$mentorUser = $this->getTestUser()->getUser();
		$mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser,
			''
		);
		$this->assertTrue( $mentorProvider->isMentor( $mentorUser ) );

		/** @var string $html */
		list( $html, ) = $this->executeSpecialPage( '', null, null, $mentorUser );
		$this->assertNotEmpty( $html );
	}

	/**
	 * @covers ::execute
	 */
	public function testNonMentor() {
		$user = $this->getTestUser()->getUser();

		list( , $response ) = $this->executeSpecialPage( '', null, null, $user );
		$this->assertEquals(
			SpecialPage::getTitleFor( 'EnrollAsMentor' )->getFullURL(),
			$response->getHeader( 'Location' )
		);
	}
}
