<?php

namespace GrowthExperiments\Tests;

use Content;
use DerivativeContext;
use Exception;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\WikiConfigException;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWikiTestCase;
use ParserOutput;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use Title;
use WikiPage;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\MentorPageMentorManager
 * @group Database
 */
class MentorPageMentorManagerTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideInvalidMentorsList
	 * @param string $mentorsListConfig
	 * @covers \GrowthExperiments\Mentorship\MentorPageMentorManager
	 */
	public function testGetMentorForUserInvalidMentorList( $mentorsListConfig ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', $mentorsListConfig );
		$mentorManager = $this->getMentorManager();

		$this->expectException( WikiConfigException::class );
		$this->expectExceptionMessage( 'wgGEHomepageMentorsList is invalid' );

		$mentorManager->getMentorForUser( $this->getTestUser()->getUser() );
	}

	public static function provideInvalidMentorsList() {
		return [
			[ null ],
			[ '' ],
			[ '  ' ],
			[ ':::' ],
			[ 'NonExistentPage' ],
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
		$this->expectExceptionMessage( 'no mentor available' );

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
		$user->setOption( MentorPageMentorManager::MENTOR_PREF, $sysop->getId() );
		$user->saveSettings();
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
		$mentee->setOption( MentorPageMentorManager::MENTOR_PREF, $mentorUser->getId() );
		$mentee->saveSettings();
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
		$mentee->setOption( MentorPageMentorManager::MENTOR_PREF, $mentorUser->getId() );
		$mentee->saveSettings();
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
	 * @param IContextSource|null $context
	 * @param array[] $pages title as prefixed text => content
	 * @return MentorPageMentorManager
	 */
	private function getMentorManager( IContextSource $context = null, array $pages = [] ) {
		$coreServices = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $coreServices );
		$context = $context ?? RequestContext::getMain();
		return new MentorPageMentorManager(
			$coreServices->getTitleFactory(),
			$pages ? $this->getMockWikiPageFactory( $pages )
				: $coreServices->getWikiPageFactory(),
			$coreServices->getUserFactory(),
			$coreServices->getUserOptionsManager(),
			$coreServices->getUserNameUtils(),
			$coreServices->getActorStore(),
			$context,
			$context->getLanguage(),
			$growthServices->getConfig()->get( 'GEHomepageMentorsList' ) ?? '',
			$growthServices->getConfig()->get( 'GEHomepageManualAssignmentMentorsList' ) ?? '',
			$context->getRequest()->wasPosted()
		);
	}

	/**
	 * Mock for $wikiPage->getContent() and $wikiPage->getParserOutput->getLinks().
	 * @param array[] $pages title as prefixed text => [ content, [ mentor username, ... ] ]
	 * @return WikiPageFactory|MockObject
	 */
	private function getMockWikiPageFactory( array $pages ) {
		$wikiPageFactory = $this->getMockBuilder( WikiPageFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'newFromTitle' ] )
			->getMock();
		$wikiPageFactory->method( 'newFromTitle' )->willReturnCallback(
			function ( Title $title ) use ( $pages ) {
				[ $text, $mentors ] = $pages[$title->getPrefixedText()];
				$wikiPage = $this->getMockBuilder( WikiPage::class )
					->disableOriginalConstructor()
					->setMethods( [ 'getContent', 'getParserOutput' ] )
					->getMock();
				$content = $this->getMockBuilder( Content::class )
					->setMethods( [ 'getText' ] )
					->getMockForAbstractClass();
				$parserOutput = $this->getMockBuilder( ParserOutput::class )
					->setMethods( [ 'getLinks' ] )
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
