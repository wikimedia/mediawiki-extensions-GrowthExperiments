<?php

namespace GrowthExperiments\Tests;

use FauxRequest;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Specials\SpecialManageMentors;
use MediaWiki\User\UserIdentity;
use PermissionsError;
use SpecialPageTestBase;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialManageMentors
 */
class SpecialManageMentorsTest extends SpecialPageTestBase {

	/** @var UserIdentity */
	private $mentorUser;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();

		// SpecialManageMentors only works with structured mentor list
		$this->setMwGlobals( 'wgGEMentorProvider', MentorProvider::PROVIDER_STRUCTURED );

		// add one mentor to the system
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->mentorUser = $this->getMutableTestUser()->getUser();
		$mentor = $geServices->getMentorProvider()
			->newMentorFromUserIdentity( $this->mentorUser );
		$mentor->setIntroText( 'this is intro' );
		$geServices->getMentorWriter()
			->addMentor(
				$mentor,
				$this->mentorUser,
				'Test'
			);
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		return new SpecialManageMentors(
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserEditTracker(),
			$geServices->getMentorProvider(),
			$geServices->getMentorWriter()
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::getMentorsTableBody
	 * @covers ::getMentorsTable
	 * @covers ::getMentorAsHtmlRow
	 */
	public function testNotAuthorizedRead() {
		list( $html, ) = $this->executeSpecialPage();
		$this->assertStringContainsString(
			$this->mentorUser->getName(),
			$html
		);
		$this->assertStringContainsString(
			'this is intro',
			$html
		);
		$this->assertStringNotContainsStringIgnoringCase(
			'Remove',
			$html
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::getMentorsTableBody
	 * @covers ::getMentorsTable
	 * @covers ::getMentorAsHtmlRow
	 */
	public function testAuthorizedRead() {
		$performer = $this->getTestSysop()->getUser();
		list( $html, ) = $this->executeSpecialPage( '', null, null, $performer );
		$this->assertStringContainsStringIgnoringCase(
			$this->mentorUser->getName(),
			$html
		);
		$this->assertStringContainsStringIgnoringCase(
			'this is intro',
			$html
		);
		$this->assertStringContainsStringIgnoringCase(
			'remove',
			$html
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::handleAction
	 * @covers ::getFormByAction
	 * @covers ::parseSubpage
	 */
	public function testNotAuthorizedRemoveMentor() {
		$this->expectException( PermissionsError::class );
		$this->expectExceptionMessage( 'The action you have requested is limited to users in the group' );

		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProvider();

		$this->assertTrue( $mentorProvider->isMentor( $this->mentorUser ) );
		$this->executeSpecialPage(
			'remove-mentor/' . $this->mentorUser->getId(),
			new FauxRequest( [ 'wpreason' => 'foo' ], true )
		);
		$this->assertTrue( $mentorProvider->isMentor( $this->mentorUser ) );
	}

	/**
	 * @covers ::execute
	 * @covers ::handleAction
	 * @covers ::getFormByAction
	 * @covers ::parseSubpage
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsRemoveMentor::onSubmit
	 * @covers \GrowthExperiments\Specials\Forms\ManageMentorsRemoveMentor::onSuccess
	 */
	public function testAuthorizedRemoveMentor() {
		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProvider();

		$this->assertTrue( $mentorProvider->isMentor( $this->mentorUser ) );
		list( $html, ) = $this->executeSpecialPage(
			'remove-mentor/' . $this->mentorUser->getId(),
			new FauxRequest( [ 'wpreason' => 'foo' ], true ),
			null,
			$this->getTestSysop()->getUser()
		);
		$this->assertStringContainsString(
			'growthexperiments-manage-mentors-remove-mentor-success',
			$html
		);
		$this->assertFalse( $mentorProvider->isMentor( $this->mentorUser ) );
	}
}
