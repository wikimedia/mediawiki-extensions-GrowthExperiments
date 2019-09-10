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

	protected function setUp() {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	/**
	 * @covers ::setConfirmEmailSiteNotice
	 * @covers ::setNotice
	 */
	public function testSetConfirmEmailNotice() {
		$outputMock = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock = $this->getDefaultMessageMock();
		$messageMock->method( 'text' )
			->willReturn( 'Foo' );
		$outputMock->method( 'msg' )
			->willReturn( $messageMock );
		$skinMock = $this->getMockBuilder( \Skin::class )
			->disableOriginalConstructor()
			->getMock();
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();
		$titleMock->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->withConsecutive( [ 'WelcomeSurvey' ], [ 'Homepage' ] )
			->willReturnOnConsecutiveCalls( false, true );
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
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
			' <span class="mw-ge-homepage-confirmemail-nojs-message">Foo</span></div>',
			$siteNotice
		);
	}

	/**
	 * @covers ::setDiscoverySiteNotice
	 * @covers ::setNotice
	 */
	public function testSetDiscoverySiteNoticeSpecialWelcomeSurveySource() {
		$outputMock = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock = $this->getDefaultMessageMock();
		$messageMock->method( 'parse' )
			->willReturn( 'specialwelcomesurvey' );
		$outputMock->method( 'msg' )
			->will( $this->returnValue( $messageMock ) );
		$skinMock = $this->getMockBuilder( \Skin::class )
			->disableOriginalConstructor()
			->getMock();
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$userMock = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->getMock();
		$userMock->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->method( 'getUser' )
			->willReturn( $userMock );
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();
		$titleMock->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->withConsecutive( [ 'WelcomeSurvey' ], [ 'Homepage' ] )
			->willReturnOnConsecutiveCalls( false, true );
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
		$siteNotice = '';
		SiteNoticeGenerator::setNotice(
			'specialwelcomesurvey',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertSame(
			'<span class="mw-ge-homepage-discovery-house"></span>' .
			'<span class="mw-ge-homepage-discovery-text-content">' .
			'<h2 class="mw-ge-homepage-discovery-nojs-message"></h2>' .
			'<p class="mw-ge-homepage-discovery-nojs-banner-text">specialwelcomesurvey</p></span>',
			$siteNotice
		);
	}

	/**
	 * @covers ::setDiscoverySiteNotice
	 * @covers ::setNotice
	 */
	public function testSetDiscoverySiteNoticeWelcomeSurveyOriginalContext() {
		$outputMock = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock = $this->getDefaultMessageMock();
		$messageMock->method( 'parse' )
			->willReturn( 'welcomesurvey-originalcontext' );
		$outputMock->method( 'msg' )
			->will( $this->returnValue( $messageMock ) );
		$skinMock = $this->getMockBuilder( \Skin::class )
			->disableOriginalConstructor()
			->getMock();
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$userMock = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->getMock();
		$userMock->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->method( 'getUser' )
			->willReturn( $userMock );
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();

		$titleMock->method( 'isSpecial' )
			->willReturn( false );
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
		$siteNotice = '';
		SiteNoticeGenerator::setNotice(
			'welcomesurvey-originalcontext',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertSame(
			'<span class="mw-ge-homepage-discovery-house"></span>' .
			'<span class="mw-ge-homepage-discovery-text-content">' .
			'<h2 class="mw-ge-homepage-discovery-nojs-message"></h2>' .
			'<p class="mw-ge-homepage-discovery-nojs-banner-text">welcomesurvey-originalcontext</p></span>',
			$siteNotice
		);
	}

	/**
	 * @covers ::isWelcomeSurveyInReferer
	 * @covers ::setNotice
	 * @covers ::maybeShowIfUserAbandonedWelcomeSurvey
	 */
	public function testMaybeShowIfUserAbandonedWelcomeSurvey() {
		$outputMock = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock = $this->getDefaultMessageMock();
		$messageMock->method( 'parse' )
			->willReturn( 'welcomesurvey-originalcontext' );
		$outputMock->method( 'msg' )
			->will( $this->returnValue( $messageMock ) );
		$skinMock = $this->getMockBuilder( \Skin::class )
			->disableOriginalConstructor()
			->getMock();
		$request = new \FauxRequest();
		$request->setHeader( 'REFERER', '?title=Special:WelcomeSurvey&blah' );
		$skinMock->method( 'getRequest' )
			->willReturn( $request );
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$userMock = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->getMock();
		$userMock->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->method( 'getUser' )
			->willReturn( $userMock );
		$languageMock = $this->getMockBuilder( \Language::class )
			->disableOriginalConstructor()
			->getMock();
		$languageMock->method( 'getSpecialPageAliases' )
			->willReturn( [
				'WelcomeSurvey' => [ 'WelcomeSurvey' ]
			] );
		$skinMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();

		$titleMock->method( 'isSpecial' )
			->with( 'WelcomeSurvey' )
			->willReturn( false );
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
		$siteNotice = '';
		SiteNoticeGenerator::setNotice(
			'',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertSame(
			'<span class="mw-ge-homepage-discovery-house"></span>' .
			'<span class="mw-ge-homepage-discovery-text-content">' .
			'<h2 class="mw-ge-homepage-discovery-nojs-message"></h2>' .
			'<p class="mw-ge-homepage-discovery-nojs-banner-text">welcomesurvey-originalcontext</p></span>',
			$siteNotice
		);
	}

	/**
	 * @covers ::isWelcomeSurveyInReferer
	 * @covers ::setNotice
	 * @covers ::maybeShowIfUserAbandonedWelcomeSurvey
	 */
	public function testMaybeShowIfUserAbandonedWelcomeSurveyRefererIsNotMatched() {
		$outputMock = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock = $this->getDefaultMessageMock();
		$messageMock->method( 'parse' )
			->willReturn( 'welcomesurvey-originalcontext' );
		$outputMock->method( 'msg' )
			->willReturn( $messageMock );
		$skinMock = $this->getMockBuilder( \Skin::class )
			->disableOriginalConstructor()
			->getMock();
		$request = new \FauxRequest();
		$request->setHeader( 'REFERER', '?title=Main_Page&blah' );
		$skinMock->method( 'getRequest' )
			->willReturn( $request );
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$userMock = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->getMock();
		$userMock->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->method( 'getUser' )
			->willReturn( $userMock );
		$languageMock = $this->getMockBuilder( \Language::class )
			->disableOriginalConstructor()
			->getMock();
		$languageMock->method( 'getSpecialPageAliases' )
			->willReturn( [
				'WelcomeSurvey' => [ 'WelcomeSurvey' ]
			] );
		$skinMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();

		$titleMock->method( 'isSpecial' )
			->with( 'WelcomeSurvey' )
			->willReturn( false );
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
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
}
