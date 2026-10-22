<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\HomepageHooks;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Specials\SpecialAccountSetup
 */
class SpecialAccountSetupTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'PersonalDashboard' );
		$this->overrideConfigValue( 'GEHomeEnabled', true );
	}

	public function testAnonymousUserGetsTheLoginForm(): void {
		$this->expectException( UserNotLoggedIn::class );

		$this->runSpecialPage( $this->getServiceContainer()->getUserFactory()->newAnonymous() );
	}

	public function testUserWithoutHomeIsRedirectedToHome(): void {
		$output = $this->runSpecialPage( $this->getMutableTestUser()->getUser() );

		$this->assertSame( SpecialPage::getTitleFor( 'Home' )->getFullURL(), $output->getRedirect() );
	}

	public function testUserWhoFinishedAccountSetupIsRedirectedToHome(): void {
		$user = $this->newUserWithOptions( [ AccountSetupHooks::ACCOUNT_SETUP_MOTIVATION_PROP => 'skipped' ] );

		$output = $this->runSpecialPage( $user );

		$this->assertSame( SpecialPage::getTitleFor( 'Home' )->getFullURL(), $output->getRedirect() );
	}

	public function testNewUserGetsAccountSetup(): void {
		$output = $this->runSpecialPage( $this->newUserWithOptions( [] ) );

		$this->assertSame( '', $output->getRedirect() );
		$this->assertContains( 'ext.growthExperiments.AccountSetup', $output->getModules() );
		$this->assertContains( 'ext.growthExperiments.AccountSetup.styles', $output->getModuleStyles() );
		$this->assertStringContainsString( 'id="growthexperiments-account_setup"', $output->getHTML() );
		$jsConfigVars = $output->getJsConfigVars();
		$this->assertTrue( $jsConfigVars['wgGEAccountSetupOnHome'] );
		$this->assertSame( [], $jsConfigVars['wgGEInterestArticles'] );
		$this->assertArrayHasKey( 'wgGENewcomerTasksMaxInterestsForQueries', $jsConfigVars );
	}

	public function testThePageIsNotRegisteredWhenTheWikiFlagIsOff(): void {
		$this->overrideConfigValue( 'GEHomeEnabled', false );

		$this->assertFalse( $this->getServiceContainer()->getSpecialPageFactory()->exists( 'AccountSetup' ) );
	}

	/**
	 * @param array<string,mixed> $options Options in addition to the homepage preference
	 */
	private function newUserWithOptions( array $options ): User {
		$user = $this->getMutableTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		foreach ( $options as $name => $value ) {
			$userOptionsManager->setOption( $user, $name, $value );
		}
		$userOptionsManager->saveOptions( $user );
		return $user;
	}

	/**
	 * The page comes from SpecialPageFactory, so that the test also covers the registration in HomepageHooks.
	 */
	private function runSpecialPage( User $user ): OutputPage {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setLanguage( 'qqx' );
		$context->setTitle( SpecialPage::getTitleFor( 'AccountSetup' ) );
		$context->setOutput( new OutputPage( $context ) );

		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'AccountSetup' );
		$this->assertNotNull( $page, 'Special:AccountSetup is registered' );
		$page->setContext( $context );
		$page->run( null );

		return $context->getOutput();
	}
}
