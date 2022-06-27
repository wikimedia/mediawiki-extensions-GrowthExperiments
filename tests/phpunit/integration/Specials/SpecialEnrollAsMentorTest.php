<?php

namespace GrowthExperiments\Tests;

use FauxResponse;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Specials\SpecialEnrollAsMentor;
use PermissionsError;
use SpecialPage;
use SpecialPageTestBase;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialEnrollAsMentor
 */
class SpecialEnrollAsMentorTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();

		// SpecialEnrollAsMentor only works with structured mentor list
		$this->setMwGlobals( 'wgGEMentorProvider', MentorProvider::PROVIDER_STRUCTURED );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		return new SpecialEnrollAsMentor(
			$geServices->getMentorProvider(),
			$geServices->getMentorWriter()
		);
	}

	/**
	 * @covers ::userCanExecute
	 */
	public function testNotAuthorized() {
		$this->expectException( PermissionsError::class );
		$this->expectExceptionMessage( 'You are not allowed to execute the action you have requested' );

		$this->setMwGlobals( 'wgRevokePermissions', [ '*' => [ 'enrollasmentor' => true ] ] );
		$user = $this->getTestUser()->getUser();
		$this->executeSpecialPage( '', null, null, $user );
	}

	/**
	 * Verify execute() redirects to Special:MentorDashboard if requestor is a mentor
	 * @covers ::execute
	 */
	public function testIsMentor() {
		$this->setGroupPermissions( '*', 'enrollasmentor', true );

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

		/** @var FauxResponse $response */
		list( , $response ) = $this->executeSpecialPage( '', null, null, $mentorUser );
		$this->assertEquals(
			SpecialPage::getTitleFor( 'MentorDashboard' )->getFullURL(),
			$response->getHeader( 'Location' )
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testIsNonMentor() {
		$this->setGroupPermissions( '*', 'enrollasmentor', true );
		$user = $this->getTestUser()->getUser();

		/** @var string $html */
		list( $html, ) = $this->executeSpecialPage( '', null, null, $user );
		$this->assertNotEmpty( $html );
	}
}
