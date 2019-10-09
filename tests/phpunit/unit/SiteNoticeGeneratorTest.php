<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Homepage\SiteNoticeGenerator;
use GrowthExperiments\HomepageHooks;
use MediaWikiUnitTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;

/**
 * @coversDefaultClass \GrowthExperiments\Homepage\SiteNoticeGenerator
 */
class SiteNoticeGeneratorTest extends MediaWikiUnitTestCase {

	protected function setUp() : void {
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
		SiteNoticeGenerator::setNotice(
			HomepageHooks::CONFIRMEMAIL_QUERY_PARAM,
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertTrue( $minervaEnableNotice );
		$this->assertSame(
			'<div class="mw-ge-homepage-confirmemail-nojs mw-ge-homepage-confirmemail-nojs-desktop">' .
			'<span aria-disabled=\'false\' class=\'oo-ui-widget ' .
			'oo-ui-widget-enabled oo-ui-iconElement-icon oo-ui-icon-check oo-ui-iconElement ' .
			'oo-ui-labelElement-invisible oo-ui-flaggedElement-success oo-ui-iconWidget\'></span>' .
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
		SiteNoticeGenerator::setNotice(
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
		SiteNoticeGenerator::setNotice(
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
		$skinMock = $this->getSkinMock( \SkinMinerva::class );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->withConsecutive( [ 'WelcomeSurvey' ], [ 'Homepage' ] )
			->willReturnOnConsecutiveCalls( false, true );
		$siteNotice = '';
		$minervaEnableNotice = false;
		SiteNoticeGenerator::setNotice(
			'specialwelcomesurvey',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertTrue( $minervaEnableNotice );
		$this->assertStringMatchesFormat(
			'<div class="mw-ge-homepage-discovery-banner-mobile">' .
			'<div class="mw-ge-homepage-discovery-arrow"></div>' .
			'<div class="mw-ge-homepage-discovery-message">' .
			'<h2>growthexperiments-homepage-discovery-mobile-homepage-banner-header</h2>' .
			'<p>growthexperiments-homepage-discovery-mobile-homepage-banner-text</p>' .
			'</div><span %s></span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::setDiscoverySiteNotice
	 * @covers ::setMobileDiscoverySiteNotice
	 * @covers ::setNotice
	 */
	public function testSetDiscoverySiteMobileNoticeWelcomeSurveyOriginalContext() {
		$skinMock = $this->getSkinMock( \SkinMinerva::class );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->method( 'isSpecial' )
			->willReturn( false );
		$siteNotice = '';
		$minervaEnableNotice = false;
		SiteNoticeGenerator::setNotice(
			'welcomesurvey-originalcontext',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertTrue( $minervaEnableNotice );
		$this->assertStringMatchesFormat(
			'<div class="mw-ge-homepage-discovery-banner-mobile">' .
			'<div class="mw-ge-homepage-discovery-arrow"></div>' .
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
			->with( 'WelcomeSurvey' )
			->willReturn( false );
		$siteNotice = '';
		$minervaEnableNotice = false;
		SiteNoticeGenerator::setNotice(
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
		SiteNoticeGenerator::setNotice(
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
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function getDefaultMessageMock() {
		$messageMock = $this->getMockBuilder( \Message::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock->method( 'params' )
			->will( $this->returnValue( $messageMock ) );
		$messageMock->method( 'rawParams' )
			->will( $this->returnValue( $messageMock ) );
		return $messageMock;
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|\Skin
	 */
	private function getSkinMock( $class = \Skin::class ) {
		$skinMock = $this->getMockBuilder( $class )
			->disableOriginalConstructor()
			->getMock();
		$outputMock = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
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
			->getMock();
		$skinMock->method( 'getUser' )
			->willReturn( $userMock );
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
		return $skinMock;
	}
}
