<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\EditInfoService;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Specials\SpecialHomepage;
use HashConfig;
use IContextSource;
use MediaWiki\Extensions\PageViewInfo\PageViewService;
use MediaWiki\MediaWikiServices;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use SpecialPageTestBase;
use User;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialHomepage
 */
class SpecialHomepageTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new SpecialHomepage(
			new class extends EditInfoService {
				public function getEditsPerDay() {
					return 0;
				}
			},
			$this->db,
			MediaWikiServices::getInstance()->get( 'GrowthExperimentsConfigurationLoader' ),
			MediaWikiServices::getInstance()->get( 'GrowthExperimentsNewcomerTaskTrackerFactory' )
		);
	}

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleDisabledPreference
	 */
	public function testHomepageDoesNotRenderWhenPreferenceIsDisabled() {
		$user = $this->getTestUser()->getUser();

		$this->expectException( \ErrorPageError::class );
		$this->expectExceptionMessage( 'To enable the newcomer homepage, visit your' );

		$this->executeSpecialPage( '', null, null, $user );
	}

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleDisabledPreference
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::execute
	 */
	public function testHomepageRendersWhenPreferenceIsEnabled() {
		$this->setMwGlobals( [
			'wgGEHomepageEnabled' => true,
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTitle',
		] );
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_PT_LINK, 1 );
		$user->saveSettings();
		$response = $this->executeSpecialPage( '', null, null, $user );
		$this->assertContains( 'growthexperiments-homepage-container', $response[0] );
	}

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleTutorialVisit
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::execute
	 */
	public function testHomepageRedirectsForTutorialVisit() {
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$this->setupForTutorialVisitTests( $user );
		list( $responseBody, $webResponse ) = $this->executeSpecialPage(
			'Main_Page',
			new \FauxRequest( [], true ),
			null,
			$user );
		$this->assertEquals(
			'',
			$responseBody,
			'Empty body since we are redirecting to tutorial title.'
		);
		$this->assertContains(
			'Main_Page',
			$webResponse->getHeader( 'location' ),
			'Location contains faux Tutorial title name'
		);
	}

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleTutorialVisit
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::execute
	 */
	public function testHompageNoTutorialTitle() {
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$this->setupForTutorialVisitTests( $user );
		$this->setMwGlobals( [ 'wgGEHomepageTutorialTitle' => 'Bogus_Title' ] );
		list( $responseBody, $webResponse ) = $this->executeSpecialPage(
			'Main_Page',
			new \FauxRequest( [], true ),
			null,
			$user );
		$this->assertContains(
			'growthexperiments-homepage-container',
			$responseBody,
			'Homepage content found'
		);
	}

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::generatePageviewToken
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::verifyPageviewToken
	 */
	public function testPageviewToken() {
		$editInfoService = $this->getMockBuilder( EditInfoService::class )
			->disableOriginalConstructor()
			->setMethodsExcept()
			->getMock();
		$editInfoService->expects( $this->never() )->method( $this->anything() );
		$pageViewService = $this->getMockBuilder( PageViewService::class )
			->disableOriginalConstructor()
			->setMethodsExcept()
			->getMock();
		$pageViewService->expects( $this->never() )->method( $this->anything() );
		$homepage = new SpecialHomepage(
			$editInfoService,
			MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA ),
			MediaWikiServices::getInstance()->get( 'GrowthExperimentsConfigurationLoader' ),
			MediaWikiServices::getInstance()->get( 'GrowthExperimentsNewcomerTaskTrackerFactory' ),
			$pageViewService
		);
		$wrappedHomepage = TestingAccessWrapper::newFromObject( $homepage );

		$context1 = $this->getContextForUserId( 1 );
		$context2 = $this->getContextForUserId( 2 );
		$homepage->setContext( $context1 );
		$pageviewContext1Token1 = $wrappedHomepage->generatePageviewToken();
		$pageviewContext1Token2 = $wrappedHomepage->generatePageviewToken();
		$homepage->setContext( $context2 );
		$pageviewContext2Token = $wrappedHomepage->generatePageviewToken();

		$this->assertNotSame( $pageviewContext1Token1, $pageviewContext1Token2 );
		$this->assertTrue( SpecialHomepage::verifyPageviewToken( $pageviewContext1Token1, $context1 ) );
		$this->assertTrue( SpecialHomepage::verifyPageviewToken( $pageviewContext2Token, $context2 ) );
		$this->assertFalse( SpecialHomepage::verifyPageviewToken( $pageviewContext1Token1, $context2 ) );
		$this->assertFalse( SpecialHomepage::verifyPageviewToken( $pageviewContext2Token, $context1 ) );
	}

	private function setupForTutorialVisitTests( User $user ) {
		$this->setMwGlobals( [
			'wgGEHomepageEnabled' => true,
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTitle',
			'wgGEHomepageTutorialTitle' => 'Main_Page',
		] );
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_PT_LINK, 1 );
		$user->saveSettings();
	}

	/**
	 * @param int $id
	 * @return IContextSource|MockObject
	 */
	private function getContextForUserId( int $id ) {
		$context = $this->getMockBuilder( RequestContext::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getConfig', 'getUser' ] )
			->getMock();
		$config = new HashConfig( [ 'SecretKey' => '42' ] );
		$context->method( 'getConfig' )->willReturn( $config );
		$user = User::newFromId( $id );
		$context->method( 'getUser' )->willReturn( $user );
		return $context;
	}
}
