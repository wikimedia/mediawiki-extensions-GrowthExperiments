<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\TourHooks;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * The homepage discovery tour and the mobile discovery notice must not show on
 * Special:AccountSetup, because the account setup dialog covers the page.
 *
 * @group Database
 * @covers \GrowthExperiments\TourHooks::onBeforePageDisplay
 * @covers \GrowthExperiments\HomepageHooks::onSiteNoticeAfter
 */
class AccountSetupDiscoveryTest extends MediaWikiIntegrationTestCase {

	/** @var IJobSpecification[] */
	private array $pushedJobs = [];

	protected function setUp(): void {
		parent::setUp();
		$this->pushedJobs = [];
		$jobQueueGroup = $this->createNoOpMock( JobQueueGroup::class, [ 'lazyPush' ] );
		$jobQueueGroup->method( 'lazyPush' )->willReturnCallback( function ( $job ) {
			$this->pushedJobs[] = $job;
		} );
		$this->setService( 'JobQueueGroup', $jobQueueGroup );
	}

	public function testNoDiscoveryTourOnAccountSetup(): void {
		if ( !TourHooks::growthTourDependenciesLoaded() ) {
			$this->markTestSkipped( 'GuidedTour, Echo and EventLogging are needed for the tour.' );
		}
		$user = $this->newNewcomer( [ TourHooks::TOUR_COMPLETED_HOMEPAGE_DISCOVERY => 0 ] );

		$this->assertNotContains(
			'ext.guidedTour.tour.homepage_discovery',
			$this->runBeforePageDisplay( $user, SpecialPage::getTitleFor( 'AccountSetup' ) )->getModules()
		);
		// Make sure that the assertion above does not pass for an unrelated reason.
		$this->assertContains(
			'ext.guidedTour.tour.homepage_discovery',
			$this->runBeforePageDisplay( $user, Title::makeTitle( NS_MAIN, 'Some article' ) )->getModules()
		);
	}

	public function testNoMobileDiscoveryNoticeOnAccountSetup(): void {
		if ( !class_exists( SkinMinerva::class ) ) {
			$this->markTestSkipped( 'Minerva is not available.' );
		}
		$user = $this->newNewcomer( [ HomepageHooks::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN => 0 ] );

		$siteNotice = $this->runSiteNoticeAfter( $user, SpecialPage::getTitleFor( 'AccountSetup' ) );
		$this->assertStringNotContainsString( 'mw-ge-homepage-discovery-banner-mobile', $siteNotice );
		// The notice is not marked as seen, so the user gets it on the next page.
		$this->assertSame( [], $this->pushedJobs );

		$siteNotice = $this->runSiteNoticeAfter( $user, Title::makeTitle( NS_MAIN, 'Some article' ) );
		$this->assertStringContainsString( 'mw-ge-homepage-discovery-banner-mobile', $siteNotice );
	}

	/**
	 * @param array<string,mixed> $options Options in addition to the homepage preference
	 */
	private function newNewcomer( array $options ): User {
		$user = $this->getMutableTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$userOptionsManager->setOption( $user, TourHooks::TOUR_COMPLETED_HOMEPAGE_WELCOME, 0 );
		foreach ( $options as $name => $value ) {
			$userOptionsManager->setOption( $user, $name, $value );
		}
		$userOptionsManager->saveOptions( $user );
		return $user;
	}

	private function runBeforePageDisplay( User $user, Title $title ): OutputPage {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setTitle( $title );
		$out = new OutputPage( $context );
		$context->setOutput( $out );

		$services = $this->getServiceContainer();
		$tourHooks = new TourHooks(
			$services->getUserOptionsLookup(),
			GrowthExperimentsServices::wrap( $services )->getFeatureManager(),
			$services->getJobQueueGroup(),
		);
		$tourHooks->onBeforePageDisplay( $out, $this->createMock( Skin::class ) );
		return $out;
	}

	private function runSiteNoticeAfter( User $user, Title $title ): string {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setTitle( $title );
		$context->setOutput( new OutputPage( $context ) );
		$skin = $this->getServiceContainer()->getSkinFactory()->makeSkin( 'minerva' );
		$skin->setContext( $context );

		$siteNotice = '';
		( new HookRunner( $this->getServiceContainer()->getHookContainer() ) )
			->onSiteNoticeAfter( $siteNotice, $skin );
		return $siteNotice;
	}
}
