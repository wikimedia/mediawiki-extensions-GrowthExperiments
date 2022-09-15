<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Specials\SpecialManageMentors;
use MediaWiki\User\UserIdentity;
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
}
