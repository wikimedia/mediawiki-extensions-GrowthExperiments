<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Provider\WikitextMentorProvider;
use MediaWikiIntegrationTestCase;
use Title;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\Provider\WikitextMentorProvider
 * @group Database
 */
class WikitextMentorProviderTest extends MediaWikiIntegrationTestCase {

	private function getMentorProvider() {
		$coreServices = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $coreServices );

		return new WikitextMentorProvider(
			$coreServices->getMainWANObjectCache(),
			$coreServices->getLocalServerObjectCache(),
			$growthServices->getMentorWeightManager(),
			$coreServices->getTitleFactory(),
			$coreServices->getWikiPageFactory(),
			$coreServices->getUserNameUtils(),
			$coreServices->getUserIdentityLookup(),
			$coreServices->getContentLanguage(),
			$growthServices->getGrowthConfig()->get( 'GEHomepageMentorsList' ) ?: null,
			$growthServices->getGrowthConfig()->get( 'GEHomepageManualAssignmentMentorsList' ) ?: null
		);
	}

	/**
	 * @covers ::getSignupTitle
	 */
	public function testGetSignupTitle() {
		$signupTitle = Title::newFromText( 'Foo bar' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', $signupTitle->getPrefixedText() );

		$mentorProvider = $this->getMentorProvider();
		$this->assertTrue( $signupTitle->equals( $mentorProvider->getSignupTitle() ) );
	}

	/**
	 * @covers ::newMentorFromUserIdentity
	 */
	public function testNewMentorFromUserIdentity() {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getMutableTestUser()->getUser();
		$secondMentor = $this->getMutableTestUser()->getUser();
		$this->insertPage(
			'MentorsList',
			'[[User:' . $mentorUser->getName() . ']]|Description
			[[User:' . $secondMentor->getName() . ']]'
		);

		$mentorProvider = $this->getMentorProvider();
		$this->assertEquals(
			'"Description"',
			$mentorProvider->newMentorFromUserIdentity( $mentorUser )->getIntroText()
		);
	}

	/**
	 * @covers ::getAutoAssignedMentors
	 * @covers ::getWeightedAutoAssignedMentors
	 * @dataProvider provideEmptyMentorsList
	 * @param string $mentorListConfig
	 */
	public function testEmptyAutoAssignedMentorList( $mentorListConfig ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', $mentorListConfig );

		$mentorProvider = $this->getMentorProvider();
		$this->assertCount( 0, $mentorProvider->getAutoAssignedMentors() );
		$this->assertCount( 0, $mentorProvider->getWeightedAutoAssignedMentors() );
	}

	/**
	 * @covers ::getManuallyAssignedMentors
	 * @dataProvider provideEmptyMentorsList
	 */
	public function testEmptyManuallyAssignedMentorList( $mentorListConfig ) {
		$this->setMwGlobals( 'wgGEHomepageManualAssignmentMentorsList', $mentorListConfig );

		$mentorProvider = $this->getMentorProvider();
		$this->assertCount( 0, $mentorProvider->getManuallyAssignedMentors() );
	}

	public function provideEmptyMentorsList() {
		return [
			[ '' ],
			[ null ]
		];
	}

	/**
	 * @covers ::getAutoAssignedMentors
	 */
	public function testGetMentorsNoInvalidUsers() {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getMutableTestUser()->getUser();
		$secondMentor = $this->getMutableTestUser()->getUser();
		$this->insertPage(
			'MentorsList',
			'[[User:' . $mentorUser->getName() . ']]
			[[User:' . $secondMentor->getName() . ']]
			[[User:This user does not exist]]'
		);

		$mentorProvider = $this->getMentorProvider();
		$autoAssignedMentors = $mentorProvider->getAutoAssignedMentors();
		$this->assertArrayEquals( [
			$mentorUser->getName(),
			$secondMentor->getName(),
		], $autoAssignedMentors );
	}

	/**
	 * @covers ::getAutoAssignedMentors
	 */
	public function testGetAutoAssignedMentors() {
		$firstMentor = $this->getMutableTestUser()->getUser();
		$secondMentor = $this->getMutableTestUser()->getUser();

		$this->insertPage(
			'MentorsList',
			'[[User:' . $firstMentor->getName() . ']]
			[[User:' . $secondMentor->getName() . ']]'
		);
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$this->assertArrayEquals(
			[ $firstMentor->getName(), $secondMentor->getName() ],
			$this->getMentorProvider()->getAutoAssignedMentors()
		);
	}

	/**
	 * @covers ::getWeightedAutoAssignedMentors
	 */
	public function testGetWeightedAutoAssignedMentors() {
		$mentorHigh = $this->getMutableTestUser()->getUser();
		$mentorLow = $this->getMutableTestUser()->getUser();
		$this->insertPage(
			'MentorsList',
			'[[User:' . $mentorHigh->getName() . ']]
			[[User:' . $mentorLow->getName() . ']]'
		);
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$uom = $this->getServiceContainer()->getUserOptionsManager();
		$provider = $this->getMentorProvider();

		// first, test without setting any weights
		$this->assertArrayEquals(
			[
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorLow->getName(),
				$mentorLow->getName(),
			],
			$provider->getWeightedAutoAssignedMentors()
		);

		// set high's weight to 4 and check again
		$uom->setOption(
			$mentorHigh,
			MentorWeightManager::MENTORSHIP_WEIGHT_PREF,
			4
		);
		$uom->saveOptions( $mentorHigh );
		$this->assertArrayEquals(
			[
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorLow->getName(),
				$mentorLow->getName(),
			],
			$provider->getWeightedAutoAssignedMentors()
		);

		// set low's weight to 1 and check again
		$uom->setOption(
			$mentorLow,
			MentorWeightManager::MENTORSHIP_WEIGHT_PREF,
			1
		);
		$uom->saveOptions( $mentorLow );
		$this->assertArrayEquals(
			[
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorLow->getName(),
			],
			$provider->getWeightedAutoAssignedMentors()
		);
	}
}
