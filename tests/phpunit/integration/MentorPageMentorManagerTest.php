<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use Exception;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\MentorPageMentorManager
 * @group Database
 */
class MentorPageMentorManagerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideInvalidMentorsList
	 * @param string $mentorsListConfig
	 * @param string $exceptionMessage
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 */
	public function testGetMentorForUserInvalidMentorList( $mentorsListConfig, $exceptionMessage ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', $mentorsListConfig );
		$mentorManager = $this->getMentorManager();

		$this->expectException( WikiConfigException::class );
		$this->expectExceptionMessage( $exceptionMessage );

		$mentorManager->getMentorForUser( $this->getTestUser()->getUser() );
	}

	public static function provideInvalidMentorsList() {
		return [
			[ '  ', 'wgGEHomepageMentorsList is invalid' ],
			[ ':::', 'wgGEHomepageMentorsList is invalid' ],
			[ 'Mentor|list', 'wgGEHomepageMentorsList is invalid' ],
			[ 'NonExistentPage', 'Page defined by wgGEHomepageMentorsList does not exist' ],
		];
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 */
	public function testRenderInvalidMentor() {
		$this->insertPage( 'MentorsList', '[[User:InvalidUser]]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorManager = $this->getMentorManager();

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No mentor available' );

		$mentorManager->getMentorForUser( $this->getTestUser()->getUser() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 * @covers \GrowthExperiments\Mentorship\Mentor::getMentorUser
	 */
	public function testGetMentorUserNew() {
		$sysop = $this->getTestSysop()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $sysop->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorManager = $this->getMentorManager();

		$mentor = $mentorManager->getMentorForUser( $this->getTestUser()->getUser() );
		$this->assertEquals( $sysop->getId(), $mentor->getMentorUser()->getId() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 * @covers \GrowthExperiments\Mentorship\Mentor::getMentorUser
	 */
	public function testGetMentorUserExisting() {
		$sysop = $this->getTestSysop()->getUser();
		$user = $this->getMutableTestUser()->getUser();
		GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore()
			->setMentorForUser( $user, $sysop, MentorStore::ROLE_PRIMARY );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorManager = $this->getMentorManager( null, [
			'MentorsList' => [ '* [[User:Mentor]]', [ 'Mentor' ] ],
		] );

		$mentor = $mentorManager->getMentorForUser( $user );
		$this->assertEquals( $sysop->getName(), $mentor->getMentorUser()->getName() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 */
	public function testMentorCannotBeMentee() {
		$user = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $user->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorManager = $this->getMentorManager();

		$this->expectException( WikiConfigException::class );

		$mentorManager->getMentorForUser( $user );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 */
	public function testMentorCannotBeMenteeMoreMentors() {
		$userMentee = $this->getMutableTestUser()->getUser();
		$userMentor = $this->getTestSysop()->getUser();
		$this->insertPage(
			'MentorsList',
			'[[User:' .
			$userMentee->getName() .
			']][[User:' .
			$userMentor->getName() .
			']]'
		);
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorManager = $this->getMentorManager();

		$mentor = $mentorManager->getMentorForUser( $userMentee );
		$this->assertEquals( $mentor->getMentorUser()->getName(), $userMentor->getName() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 */
	public function testNoMentorAvailable() {
		$this->insertPage( 'MentorsList', 'Mentors' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorManager = $this->getMentorManager();

		$this->expectException( WikiConfigException::class );

		$mentorManager->getMentorForUser( $this->getMutableTestUser()->getUser() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 * @covers \GrowthExperiments\Mentorship\Mentor::getIntroText
	 * @dataProvider provideCustomMentorText
	 */
	public function testRenderMentorText( $mentorsList, $expected ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', str_replace( '$1', $mentorUser->getName(), $mentorsList ) );
		GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore()
			->setMentorForUser(
				$mentee,
				$mentorUser,
				MentorStore::ROLE_PRIMARY
			);
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$mentorManager = $this->getMentorManager( $context );

		$mentor = $mentorManager->getMentorForUser( $mentee );
		$this->assertEquals( $expected, $mentor->getIntroText() );
	}

	public function provideCustomMentorText() {
		$fallback = 'This experienced user knows you\'re new and can help you with editing.';
		return [
			[ '[[User:$1]] | This is a sample text.', '"This is a sample text."' ],
			[ '[[User:$1]]|This is a sample text.', '"This is a sample text."' ],
			[ "[[User:$1]]|This is a sample text.\n[[User:Foobar]]|More text", '"This is a sample text."' ],
			[ '[[User:$1]] This is a sample text', $fallback ],
			[ '[[User:$1]]', $fallback ],
			[ "[[User:\$1]]|\n[[User:Foobar]]|This is a sample text.", $fallback ]
		];
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 * @covers \GrowthExperiments\Mentorship\Mentor::getIntroText
	 */
	public function testRenderFallbackMentorText() {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $mentorUser->getName() . ']]' );
		GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore()
			->setMentorForUser(
				$mentee,
				$mentorUser,
				MentorStore::ROLE_PRIMARY
			);
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$mentorManager = $this->getMentorManager( $context );

		$mentor = $mentorManager->getMentorForUser( $mentee );
		$this->assertStringContainsString(
			'This experienced user knows you\'re new and can help you with editing.',
			$mentor->getIntroText()
		);
	}

	/**
	 * @covers ::getMentorshipStateForUser
	 */
	public function testGetMentorshipStateForUser() {
		$optionManager = $this->getServiceContainer()->getUserOptionsManager();

		$enabledUser = $this->getMutableTestUser()->getUser();
		$optionManager->setOption(
			$enabledUser,
			MentorPageMentorManager::MENTORSHIP_ENABLED_PREF,
			1
		);
		$optionManager->saveOptions( $enabledUser );

		$disabledUser = $this->getMutableTestUser()->getUser();
		$optionManager->setOption(
			$disabledUser,
			MentorPageMentorManager::MENTORSHIP_ENABLED_PREF,
			0
		);
		$optionManager->saveOptions( $disabledUser );

		$optedOutUser = $this->getMutableTestUser()->getUser();
		$optionManager->setOption(
			$optedOutUser,
			MentorPageMentorManager::MENTORSHIP_ENABLED_PREF,
			2
		);
		$optionManager->saveOptions( $optedOutUser );

		$mentorManager = $this->getMentorManager();
		$this->assertEquals(
			MentorManager::MENTORSHIP_ENABLED,
			$mentorManager->getMentorshipStateForUser( $enabledUser )
		);
		$this->assertEquals(
			MentorManager::MENTORSHIP_DISABLED,
			$mentorManager->getMentorshipStateForUser( $disabledUser )
		);
		$this->assertEquals(
			MentorManager::MENTORSHIP_OPTED_OUT,
			$mentorManager->getMentorshipStateForUser( $optedOutUser )
		);
	}

	/**
	 * @covers ::getMentorshipStateForUser
	 */
	public function testGetMentorshipStateForUserBroken() {
		$optionManager = $this->getServiceContainer()->getUserOptionsManager();
		$brokenUser = $this->getMutableTestUser()->getUser();

		$optionManager->setOption(
			$brokenUser,
			MentorPageMentorManager::MENTORSHIP_ENABLED_PREF,
			123
		);
		$optionManager->saveOptions( $brokenUser );

		$this->assertEquals(
			MentorManager::MENTORSHIP_DISABLED,
			$this->getMentorManager()->getMentorshipStateForUser( $brokenUser )
		);
	}

	/**
	 * @param IContextSource|null $context
	 * @param array[] $pages title as prefixed text => content
	 * @return MentorPageMentorManager
	 */
	private function getMentorManager( IContextSource $context = null, array $pages = [] ) {
		$coreServices = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $coreServices );
		$context = $context ?? RequestContext::getMain();

		return new MentorPageMentorManager(
			$growthServices->getMentorStore(),
			$growthServices->getMentorStatusManager(),
			$growthServices->getMentorProvider(),
			$coreServices->getUserIdentityLookup(),
			$coreServices->getUserOptionsLookup(),
			$coreServices->getUserOptionsManager(),
			$context->getRequest()->wasPosted()
		);
	}
}
