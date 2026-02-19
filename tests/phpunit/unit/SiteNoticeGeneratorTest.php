<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Homepage\SiteNoticeGenerator;
use GrowthExperiments\HomepageHooks;
use MediaWiki\Config\HashConfig;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Language\Language;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use OOUI\BlankTheme;
use OOUI\IconWidget;
use OOUI\Theme;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \GrowthExperiments\Homepage\SiteNoticeGenerator
 */
class SiteNoticeGeneratorTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	public function testSetConfirmEmailNotice() {
		$skinMock = $this->getSkinMock();
		$skinMock->getTitle()->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->willReturnMap( [
				[ 'WelcomeSurvey', false ],
				[ 'Homepage', true ],
			] );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig(),
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

	public function testSetDiscoverySiteNoticeDesktopSpecialWelcomeSurveySource() {
		$skinMock = $this->getSkinMock();
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->willReturnMap( [
				[ 'WelcomeSurvey', false ],
				[ 'Homepage', true ],
			] );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig(),
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

	public function testSetDiscoverySiteDesktopNoticeWelcomeSurveyOriginalContext() {
		$skinMock = $this->getSkinMock();
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->method( 'isSpecial' )
			->willReturn( false );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig(),
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

	public function testSetDiscoverySiteNoticeMobileSpecialWelcomeSurveySource() {
		if ( !class_exists( SkinMinerva::class ) ) {
			$this->markTestSkipped( 'Minerva is not available.' );
		}
		$skinMock = $this->getSkinMock( SkinMinerva::class );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$skinMock->getTitle()->expects( $this->exactly( 2 ) )
			->method( 'isSpecial' )
			->willReturnMap( [
				[ 'WelcomeSurvey', false ],
				[ 'Homepage', true ],
			] );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig(),
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
			'<div class="mw-ge-homepage-discovery-arrow"></div>' .
			'<div class="mw-ge-homepage-discovery-message">' .
			'<p>growthexperiments-homepage-discovery-mobile-homepage-banner-text</p>' .
			'</div><span %s></span></div>',
			$siteNotice
		);
	}

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
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig(),
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
			'<div class="mw-ge-homepage-discovery-arrow"></div>' .
			'<div class="mw-ge-homepage-discovery-message">' .
			'<h2>growthexperiments-homepage-discovery-mobile-nonhomepage-banner-header</h2>' .
			'<p>growthexperiments-homepage-discovery-mobile-nonhomepage-banner-text</p>' .
			'</div><span %s></span></div>',
			$siteNotice
		);
	}

	public function testSetDiscoverySiteMobileNoticeWelcomeSurveyOriginalContext_PersonalMenu() {
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
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig( [
				'MinervaPersonalMenu' => [
					'loggedin' => true,
				],
			] ),
		);
		$siteNoticeGenerator->setNotice(
			'welcomesurvey-originalcontext',
			$siteNotice,
			$skinMock,
			$minervaEnableNotice
		);
		$this->assertTrue( $minervaEnableNotice );
		$classString = implode( ' ', [
			'mw-ge-homepage-discovery-banner-mobile',
			'mw-ge-homepage-discovery-banner-mobile__with-personal-menu',
		] );
		$this->assertStringMatchesFormat(
			'<div class="' . $classString . '">' .
			'<div class="mw-ge-homepage-discovery-message">' .
			'<h2>growthexperiments-homepage-discovery-mobile-nonhomepage-banner-header</h2>' .
			'<p>growthexperiments-homepage-discovery-mobile-nonhomepage-banner-text</p>' .
			'</div><span %s></span><div class="mw-ge-homepage-discovery-arrow"></div></div>',
			$siteNotice
		);
	}

	public function testMaybeShowIfUserAbandonedWelcomeSurvey() {
		$skinMock = $this->getSkinMock();
		$request = new FauxRequest();
		$request->setHeader( 'REFERER', '?title=Special:WelcomeSurvey&blah' );
		$skinMock->method( 'getRequest' )
			->willReturn( $request );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$languageMock = $this->createMock( Language::class );
		$languageMock->method( 'getSpecialPageAliases' )
			->willReturn( [
				'WelcomeSurvey' => [ 'WelcomeSurvey' ],
			] );
		$skinMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$skinMock->getTitle()->method( 'isSpecial' )
			->willReturn( false );
		$siteNotice = '';
		$minervaEnableNotice = false;
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig(),
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

	public function testMaybeShowIfUserAbandonedWelcomeSurveyRefererIsNotMatched() {
		$skinMock = $this->getSkinMock();
		$request = new FauxRequest();
		$request->setHeader( 'REFERER', '?title=Main_Page&blah' );
		$skinMock->method( 'getRequest' )
			->willReturn( $request );
		$skinMock->getUser()->method( 'getName' )
			->willReturn( 'Bar' );
		$languageMock = $this->createMock( Language::class );
		$languageMock->method( 'getSpecialPageAliases' )
			->willReturn( [
				'WelcomeSurvey' => [ 'WelcomeSurvey' ],
			] );
		$skinMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$skinMock->getTitle()->method( 'isSpecial' )
			->with( 'WelcomeSurvey' )
			->willReturn( false );
		$siteNotice = '';
		$siteNoticeGenerator = new SiteNoticeGenerator(
			$this->getUserOptionsLookupMock(),
			$this->getJobQueueGroupMock(),
			new HashConfig(),
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
	 * @param class-string<Skin> $class
	 * @return MockObject|Skin
	 */
	private function getSkinMock( $class = Skin::class ) {
		$outputMock = $this->createMock( OutputPage::class );
		$outputMock->method( 'msg' )->willReturnCallback(
			fn ( $k, ...$p ) => $this->getMockMessage( $k, $p )
		);

		$userMock = $this->createNoOpMock( User::class, [ 'getId', 'getName' ] );
		// This will make user settings update job fail, but we don't care about that.
		$userMock->method( 'getId' )
			->willReturn( -1 );
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getOption' )
			->with( $this->anything(), HomepageHooks::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN )
			->willReturn( true );

		$titleMock = $this->createMock( Title::class );

		$skinMock = $this->createMock( $class );
		$skinMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$skinMock->method( 'getUser' )
			->willReturn( $userMock );
		$skinMock->method( 'getTitle' )
			->willReturn( $titleMock );
		return $skinMock;
	}

	/**
	 * @return UserOptionsLookup|MockObject
	 */
	private function getUserOptionsLookupMock() {
		$mock = $this->createMock( UserOptionsLookup::class );
		$mock->method( 'getOption' )
			->willReturn( true );
		return $mock;
	}

	/**
	 * @return JobQueueGroup|MockObject
	 */
	private function getJobQueueGroupMock() {
		$mock = $this->createMock( JobQueueGroup::class );
		return $mock;
	}
}
