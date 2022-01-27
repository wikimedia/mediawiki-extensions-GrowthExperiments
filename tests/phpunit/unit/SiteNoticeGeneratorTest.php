<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\Homepage\SiteNoticeGenerator;
use GrowthExperiments\HomepageHooks;
use JobQueueGroup;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use OOUI\BlankTheme;
use OOUI\IconWidget;
use OOUI\Theme;
use PHPUnit\Framework\MockObject\MockObject;
use Skin;
use SkinMinerva;

/**
 * @coversDefaultClass \GrowthExperiments\Homepage\SiteNoticeGenerator
 */
class SiteNoticeGeneratorTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	/**
	 * @covers ::setConfirmEmailSiteNotice
	 * @covers ::setNotice
	 */
	public function testSetConfirmEmailNotice() {
		$skinMock = $this->getSkinMock();
		$skinMock->getTitle()->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->withConsecutive( [ 'WelcomeSurvey' ], [ 'Homepage' ] )
			->willReturnOnConsecutiveCalls( false, true );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getExperimentUserManagerMock(),
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock()
		);
		$siteNoticeGenerator->setNotice(
			HomepageHooks::CONFIRMEMAIL_QUERY_PARAM,
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertTrue( $minervaEnableNotice );
		$this->assertSame(
			'<div class="mw-ge-homepage-confirmemail-nojs mw-ge-homepage-confirmemail-nojs-desktop">' .
			new IconWidget( [ 'icon' => 'check', 'flags' => 'success' ] ) .
			' <span class="mw-ge-homepage-confirmemail-nojs-message">confirmemail_loggedin</span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::setDiscoverySiteNotice
	 * @covers ::setDesktopDiscoverySiteNotice
	 * @covers ::setNotice
	 */
	public function testSetDiscoverySiteNoticeDesktopSpecialWelcomeSurveySource() {
		$skinMock = $this->getSkinMock();
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->withConsecutive( [ 'WelcomeSurvey' ], [ 'Homepage' ] )
			->willReturnOnConsecutiveCalls( false, true );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getExperimentUserManagerMock(),
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock()
		);
		$siteNoticeGenerator->setNotice(
			'specialwelcomesurvey',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertFalse( $minervaEnableNotice );
		$this->assertSame(
			'<div class="mw-ge-homepage-discovery-banner-nojs">' .
			'<span class="mw-ge-homepage-discovery-house"></span>' .
			'<span class="mw-ge-homepage-discovery-text-content">' .
			'<h2 class="mw-ge-homepage-discovery-nojs-message">' .
				'growthexperiments-homepage-discovery-banner-header</h2>' .
			'<p class="mw-ge-homepage-discovery-nojs-banner-text">' .
				'growthexperiments-homepage-discovery-banner-text</p>' .
			'</span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::setDiscoverySiteNotice
	 * @covers ::setDesktopDiscoverySiteNotice
	 * @covers ::setNotice
	 */
	public function testSetDiscoverySiteDesktopNoticeWelcomeSurveyOriginalContext() {
		$skinMock = $this->getSkinMock();
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->method( 'isSpecial' )
			->willReturn( false );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getExperimentUserManagerMock(),
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock()
		);
		$siteNoticeGenerator->setNotice(
			'welcomesurvey-originalcontext',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertFalse( $minervaEnableNotice );
		$this->assertSame(
			'<div class="mw-ge-homepage-discovery-banner-nojs">' .
			'<span class="mw-ge-homepage-discovery-house"></span>' .
			'<span class="mw-ge-homepage-discovery-text-content">' .
			'<h2 class="mw-ge-homepage-discovery-nojs-message">' .
				'growthexperiments-homepage-discovery-thanks-header</h2>' .
			'<p class="mw-ge-homepage-discovery-nojs-banner-text">' .
				'growthexperiments-homepage-discovery-thanks-text</p>' .
			'</span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::setDiscoverySiteNotice
	 * @covers ::setMobileDiscoverySiteNotice
	 * @covers ::setNotice
	 */
	public function testSetDiscoverySiteNoticeMobileSpecialWelcomeSurveySource() {
		if ( !class_exists( SkinMinerva::class ) ) {
			$this->markTestSkipped( 'Minerva is not available.' );
		}
		$skinMock = $this->getSkinMock( SkinMinerva::class );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->withConsecutive( [ 'WelcomeSurvey' ], [ 'Homepage' ] )
			->willReturnOnConsecutiveCalls( false, true );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getExperimentUserManagerMock(),
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock()
		);
		$siteNoticeGenerator->setNotice(
			'specialwelcomesurvey',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertTrue( $minervaEnableNotice );
		$this->assertStringMatchesFormat(
			'<div class="mw-ge-homepage-discovery-banner-mobile">' .
			'<div class="mw-ge-homepage-discovery-arrow mw-ge-homepage-discovery-arrow-user-variant-C"></div>' .
			'<div class="mw-ge-homepage-discovery-message">' .
			'<p>growthexperiments-homepage-discovery-mobile-homepage-banner-text</p>' .
			'</div><span %s></span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::setDiscoverySiteNotice
	 * @covers ::setMobileDiscoverySiteNotice
	 * @covers ::setNotice
	 * @covers ::getHeader
	 */
	public function testSetDiscoverySiteMobileNoticeWelcomeSurveyOriginalContext() {
		if ( !class_exists( SkinMinerva::class ) ) {
			$this->markTestSkipped( 'Minerva is not available.' );
		}
		$skinMock = $this->getSkinMock( SkinMinerva::class );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->method( 'isSpecial' )
			->willReturn( false );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getExperimentUserManagerMock(),
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock()
		);
		$siteNoticeGenerator->setNotice(
			'welcomesurvey-originalcontext',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertTrue( $minervaEnableNotice );
		$this->assertStringMatchesFormat(
			'<div class="mw-ge-homepage-discovery-banner-mobile">' .
			'<div class="mw-ge-homepage-discovery-arrow mw-ge-homepage-discovery-arrow-user-variant-C"></div>' .
			'<div class="mw-ge-homepage-discovery-message">' .
			'<h2>growthexperiments-homepage-discovery-mobile-nonhomepage-banner-header</h2>' .
			'<p>growthexperiments-homepage-discovery-mobile-nonhomepage-banner-text</p>' .
			'</div><span %s></span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::isWelcomeSurveyInReferer
	 * @covers ::setNotice
	 * @covers ::maybeShowIfUserAbandonedWelcomeSurvey
	 */
	public function testMaybeShowIfUserAbandonedWelcomeSurvey() {
		$skinMock = $this->getSkinMock();
		$request = new \FauxRequest();
		$request->setHeader( 'REFERER', '?title=Special:WelcomeSurvey&blah' );
		$skinMock->method( 'getRequest' )
			->willReturn( $request );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$languageMock = $this->getMockBuilder( \Language::class )
			->disableOriginalConstructor()
			->getMock();
		$languageMock->method( 'getSpecialPageAliases' )
			->willReturn( [
				'WelcomeSurvey' => [ 'WelcomeSurvey' ]
			] );
		$skinMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$skinMock->getTitle()->method( 'isSpecial' )
			->willReturnMap( [
				'WelcomeSurvey' => false,
				'Homepage' => false,
			] );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getExperimentUserManagerMock(),
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock()
		);
		$siteNoticeGenerator->setNotice(
			'',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertFalse( $minervaEnableNotice );
		$this->assertSame(
			'<div class="mw-ge-homepage-discovery-banner-nojs">' .
			'<span class="mw-ge-homepage-discovery-house"></span>' .
			'<span class="mw-ge-homepage-discovery-text-content">' .
			'<h2 class="mw-ge-homepage-discovery-nojs-message">' .
			'growthexperiments-homepage-discovery-thanks-header</h2>' .
			'<p class="mw-ge-homepage-discovery-nojs-banner-text">' .
			'growthexperiments-homepage-discovery-thanks-text</p>' .
			'</span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::isWelcomeSurveyInReferer
	 * @covers ::setNotice
	 * @covers ::maybeShowIfUserAbandonedWelcomeSurvey
	 */
	public function testMaybeShowIfUserAbandonedWelcomeSurveyRefererIsNotMatched() {
		$skinMock = $this->getSkinMock();
		$request = new \FauxRequest();
		$request->setHeader( 'REFERER', '?title=Main_Page&blah' );
		$skinMock->method( 'getRequest' )
			->willReturn( $request );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$languageMock = $this->getMockBuilder( \Language::class )
			->disableOriginalConstructor()
			->getMock();
		$languageMock->method( 'getSpecialPageAliases' )
			->willReturn( [
				'WelcomeSurvey' => [ 'WelcomeSurvey' ]
			] );
		$skinMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$skinMock->getTitle()->method( 'isSpecial' )
			->with( 'WelcomeSurvey' )
			->willReturn( false );
		$siteNotice = '';
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getExperimentUserManagerMock(),
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock()
		);
		$siteNoticeGenerator->setNotice(
			'',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertSame(
			'',
			$siteNotice
		);
	}

	/**
	 * @return MockObject|\Message
	 */
	private function getDefaultMessageMock() {
		$messageMock = $this->getMockBuilder( \Message::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock->method( 'params' )
			->willReturn( $messageMock );
		$messageMock->method( 'rawParams' )
			->willReturn( $messageMock );
		return $messageMock;
	}

	/**
	 * @param string $class
	 * @return MockObject|Skin
	 */
	private function getSkinMock( $class = Skin::class ) {
		$outputMock = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$outputMock->method( 'msg' )
			->willReturnCallback( function ( $key ) {
				$messageMock = $this->getDefaultMessageMock();
				$messageMock->method( 'text' )
					->willReturn( $key );
				$messageMock->method( 'parse' )
					->willReturn( $key );
				return $messageMock;
			} );

		$userMock = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getId', 'getName' ] )
			->getMock();
		// This will make user settings update job fail, but we don't care about that.
		$userMock->method( 'getId' )
			->willReturn( -1 );
		$userOptionsLookupMock = $this->getMockBuilder( UserOptionsLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$userOptionsLookupMock->method( 'getOption' )
			->with( $this->anything(), HomepageHooks::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN )
			->willReturn( true );

		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();

		$skinMock = $this->getMockBuilder( $class )
			->disableOriginalConstructor()
			->getMock();
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$skinMock->method( 'getUser' )
			->willReturn( $userMock );
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
		return $skinMock;
	}

	/**
	 * @param string $variant
	 * @return ExperimentUserManager|MockObject
	 */
	private function getExperimentUserManagerMock( $variant = 'C' ) {
		$mock = $this->getMockBuilder( ExperimentUserManager::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getVariant' )
			->willReturn( $variant );
		return $mock;
	}

	/**
	 * @return UserOptionsLookup|MockObject
	 */
	private function getUserOptionsLookupMock() {
		$mock = $this->getMockBuilder( UserOptionsLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getOption' )
			->willReturn( true );
		return $mock;
	}

	/**
	 * @return JobQueueGroup|MockObject
	 */
	private function getJobQueueGroupMock() {
		$mock = $this->getMockBuilder( JobQueueGroup::class )
			->disableOriginalConstructor()
			->getMock();
		return $mock;
	}
}
