<?php

namespace GrowthExperiments\Tests;

use Content;
use DerivativeContext;
use EmptyBagOStuff;
use Exception;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWikiIntegrationTestCase;
use ParserOutput;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use Title;
use Wikimedia\TestingAccessWrapper;
use WikiPage;

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
	 * @covers ::getAutoAssignedMentors
	 * @dataProvider provideEmptyMentorsList
	 * @param string $mentorListConfig
	 */
	public function testGetAutoAssignedMentorsForEmptyMentorList( $mentorListConfig ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', $mentorListConfig );
		$mentorManager = $this->getMentorManager();

		$this->assertCount( 0, $mentorManager->getAutoAssignedMentors() );
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
			$this->getMentorManager()->getAutoAssignedMentors()
		);
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
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
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
		$mentorManager = $this->getMentorManager();

		$autoAssignedMentors = $mentorManager->getAutoAssignedMentors();
		$this->assertArrayEquals( [
			$mentorUser->getName(),
			$secondMentor->getName(),
		], $autoAssignedMentors );
	}

	/**
	 * @covers ::isMentorshipEnabledForUser
	 */
	public function testIsMentorshipEnabled() {
		$optionManager = $this->getServiceContainer()->getUserOptionsManager();

		$enabledUser = $this->getMutableTestUser()->getUser();
		$disabledUser = $this->getMutableTestUser()->getUser();
		$defaultUser = $this->getMutableTestUser()->getUser();
		$optionManager->setOption( $enabledUser, MentorPageMentorManager::MENTORSHIP_ENABLED_PREF, 1 );
		$enabledUser->saveSettings();
		$optionManager->setOption( $disabledUser, MentorPageMentorManager::MENTORSHIP_ENABLED_PREF, 0 );
		$disabledUser->saveSettings();

		$manager = $this->getMentorManager();
		$this->assertTrue( $manager->isMentorshipEnabledForUser( $enabledUser ) );
		$this->assertFalse( $manager->isMentorshipEnabledForUser( $disabledUser ) );
		$this->assertTrue( $manager->isMentorshipEnabledForUser( $defaultUser ) );
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
		$manager = TestingAccessWrapper::newFromObject( $this->getMentorManager() );

		// first, test without setting any weights
		$this->assertArrayEquals(
			[
				$mentorHigh->getName(),
				$mentorHigh->getName(),
				$mentorLow->getName(),
				$mentorLow->getName(),
			],
			$manager->getWeightedAutoAssignedMentors()
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
			$manager->getWeightedAutoAssignedMentors()
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
			$manager->getWeightedAutoAssignedMentors()
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
		$manager = new MentorPageMentorManager(
			$growthServices->getMentorStore(),
			$growthServices->getMentorStatusManager(),
			$growthServices->getMentorWeightManager(),
			$coreServices->getTitleFactory(),
			$pages ? $this->getMockWikiPageFactory( $pages )
				: $coreServices->getWikiPageFactory(),
			$coreServices->getUserNameUtils(),
			$coreServices->getUserIdentityLookup(),
			$coreServices->getUserOptionsLookup(),
			$context,
			$context->getLanguage(),
			$growthServices->getGrowthConfig()->get( 'GEHomepageMentorsList' ) ?: null,
			$growthServices->getGrowthConfig()->get( 'GEHomepageManualAssignmentMentorsList' ) ?: null,
			$context->getRequest()->wasPosted()
		);
		$manager->setCache( new EmptyBagOStuff(), 0 );
		return $manager;
	}

	/**
	 * Mock for $wikiPage->getContent() and $wikiPage->getParserOutput->getLinks().
	 * @param array[] $pages title as prefixed text => [ content, [ mentor username, ... ] ]
	 * @return WikiPageFactory|MockObject
	 */
	private function getMockWikiPageFactory( array $pages ) {
		$wikiPageFactory = $this->getMockBuilder( WikiPageFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'newFromTitle' ] )
			->getMock();
		$wikiPageFactory->method( 'newFromTitle' )->willReturnCallback(
			function ( Title $title ) use ( $pages ) {
				[ $text, $mentors ] = $pages[$title->getPrefixedText()];
				$wikiPage = $this->getMockBuilder( WikiPage::class )
					->disableOriginalConstructor()
					->onlyMethods( [ 'getContent', 'getParserOutput' ] )
					->getMock();
				$content = $this->getMockBuilder( Content::class )
					->addMethods( [ 'getText' ] )
					->getMockForAbstractClass();
				$parserOutput = $this->getMockBuilder( ParserOutput::class )
					->onlyMethods( [ 'getLinks' ] )
					->getMock();
				$wikiPage->method( 'getContent' )->willReturn( $content );
				$content->method( 'getText' )->willReturn( $text );
				$wikiPage->method( 'getParserOutput' )->willReturn( $parserOutput );
				$parserOutput->method( 'getLinks' )->willReturn( [
					NS_USER => array_combine( $mentors, range( 1, count( $mentors ) ) ),
				] );

				return $wikiPage;
			}
		);
		return $wikiPageFactory;
	}

}
