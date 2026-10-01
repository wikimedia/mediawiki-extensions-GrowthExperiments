<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HomepageHooks;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Specials\SpecialHome
 */
class SpecialHomeTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'PersonalDashboard' );
	}

	/**
	 * An anonymous user must get the login form, and not the preference error
	 * page. The preference is off by default, so SpecialHome::beforeExecute()
	 * calls requireNamedUser() before it reads the preference. A change to that
	 * order shows up here, and nowhere else.
	 */
	public function testAnonymousUserGetsTheLoginForm() {
		$this->overrideConfigValue( 'GEHomeEnabled', true );

		$this->expectException( UserNotLoggedIn::class );

		$this->runSpecialPage( $this->getServiceContainer()->getUserFactory()->newAnonymous() );
	}

	/**
	 * A user without the newcomer homepage preference is not in the pilot
	 * cohort, and gets the error page that tells them how to enable Home.
	 */
	public function testUserWithoutThePreferenceGetsTheErrorPage() {
		$this->overrideConfigValue( 'GEHomeEnabled', true );
		$user = $this->getMutableTestUser()->getUser();

		try {
			$this->runSpecialPage( $user );
			$this->fail( 'SpecialHome must refuse a user without the preference' );
		} catch ( ErrorPageError $e ) {
			// ErrorPageError forces getMessage() to English, for the log files
			// (T46111), so the context language does not change it. Read the
			// message key instead. The key keeps the test independent of the
			// English text, and it also tells this error apart from the login
			// form, because UserNotLoggedIn is an ErrorPageError too.
			$this->assertSame( 'growthexperiments-home-enable-preference', $e->msg );
		}
	}

	/**
	 * A user with the preference gets the page, and the page renders the `home`
	 * module group. This also shows that the services in the registration match
	 * the SpecialHome constructor.
	 */
	public function testUserWithThePreferenceGetsTheHomeModuleGroup() {
		$this->overrideConfigValue( 'GEHomeEnabled', true );
		$user = $this->newUserWithHomepagePreference();

		[ , $output ] = $this->runSpecialPage( $user );

		$this->assertSame(
			'home',
			$output->getJsConfigVars()['wgPersonalDashboardModuleGroup'] ?? null
		);
	}

	/**
	 * The h1 greets the user by name, and the Special:SpecialPages label stays
	 * static. SpecialPage::setHeaders() takes the h1 from getDescription(), and
	 * Special:SpecialPages takes the label from the same method, so SpecialHome
	 * splits the two. A label must not hold a user name.
	 */
	public function testTheTitleHoldsTheUserNameAndTheLabelDoesNot() {
		$this->overrideConfigValue( 'GEHomeEnabled', true );
		$user = $this->newUserWithHomepagePreference();

		[ , $output ] = $this->runSpecialPage( $user );

		// The context language is qqx, so a message shows as its key, and it
		// also shows the parameters. This keeps the test independent of the
		// English text.
		$this->assertStringContainsString(
			'growthexperiments-home-specialpage-title',
			$output->getPageTitle(),
			'The h1 comes from the greeting message'
		);
		$this->assertStringContainsString(
			$user->getName(),
			$output->getPageTitle(),
			'The h1 holds the user name'
		);

		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Home' );
		$page->setContext( RequestContext::getMain() );
		$this->assertSame(
			'growthexperiments-home-tab',
			$page->getDescription()->getKey(),
			'The Special:SpecialPages label is the static message'
		);
	}

	/**
	 * GEHomeEnabled is a wiki level flag. A wiki without the feature must not
	 * have the page at all.
	 */
	public function testThePageIsNotRegisteredWhenTheWikiFlagIsOff() {
		// Read the factory again after each override. The override resets the
		// services, and SpecialPageFactory holds the page list.
		$this->overrideConfigValue( 'GEHomeEnabled', true );
		$this->assertTrue(
			$this->getServiceContainer()->getSpecialPageFactory()->exists( 'Home' ),
			'Special:Home exists when GEHomeEnabled is true'
		);

		$this->overrideConfigValue( 'GEHomeEnabled', false );
		$this->assertFalse(
			$this->getServiceContainer()->getSpecialPageFactory()->exists( 'Home' ),
			'Special:Home does not exist when GEHomeEnabled is false'
		);
	}

	private function newUserWithHomepagePreference(): User {
		$user = $this->getMutableTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$userOptionsManager->saveOptions( $user );
		return $user;
	}

	/**
	 * Run the page through SpecialPage::run().
	 *
	 * SpecialPageTestBase::executeSpecialPage() calls execute() and not run(),
	 * so it steps over beforeExecute(), where the gate is. The gate must run
	 * here, so this method calls run() itself.
	 *
	 * The page comes from SpecialPageFactory, and not from a constructor call,
	 * so that the test also covers the registration in HomepageHooks.
	 *
	 * @param User $user
	 * @param string|null $subPage
	 * @return array [ string $html, OutputPage $output ]
	 */
	private function runSpecialPage( User $user, ?string $subPage = null ): array {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setLanguage( 'qqx' );
		$context->setTitle( SpecialPage::getTitleFor( 'Home' ) );
		$context->setOutput( new OutputPage( $context ) );

		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Home' );
		$this->assertNotNull( $page, 'Special:Home is registered' );
		$page->setContext( $context );
		$page->run( $subPage );

		return [ $context->getOutput()->getHTML(), $context->getOutput() ];
	}
}
