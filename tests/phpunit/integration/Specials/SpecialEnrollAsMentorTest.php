<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialEnrollAsMentor;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxResponse;
use MediaWiki\SpecialPage\SpecialPage;
use SpecialPageTestBase;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialEnrollAsMentor
 * @group Database
 */
class SpecialEnrollAsMentorTest extends SpecialPageTestBase {
	use CommunityConfigurationTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->setMainCache( CACHE_NONE );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		return new SpecialEnrollAsMentor(
			$geServices->getGrowthWikiConfig(),
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

		$this->overrideConfigValues( [
			MainConfigNames::RevokePermissions => [ '*' => [ 'enrollasmentor' => true ] ],
		] );
		$this->overrideProviderConfig( [
			'GEMentorshipAutomaticEligibility' => false,
		], 'Mentorship' );
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
		$this->assertStatusGood( $mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser,
			''
		) );
		$this->assertTrue( $mentorProvider->isMentor( $mentorUser ) );

		/** @var FauxResponse $response */
		[ , $response ] = $this->executeSpecialPage( '', null, null, $mentorUser );
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
		[ $html ] = $this->executeSpecialPage( '', null, null, $user );
		$this->assertNotEmpty( $html );
	}
}
