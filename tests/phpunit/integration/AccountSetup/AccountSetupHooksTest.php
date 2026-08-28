<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\FeatureManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Interwiki\ClassicInterwikiLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * Integration test for the interest article which AccountSetupHooks stores at account creation.
 *
 * The unit test covers the branches of the hook with mocked collaborators. This test
 * runs the same code path against the real core services, to show that:
 * - TitleFactory normalises the returnto value into a Title.
 * - Title::isContentPage() agrees with the $wgContentNamespaces configuration.
 * - RedirectLookup resolves a real redirect page from the database.
 * - UserOptionsManager writes the interest article to user_properties.
 *
 * @covers \GrowthExperiments\AccountSetup\AccountSetupHooks
 * @group Database
 */
class AccountSetupHooksTest extends MediaWikiIntegrationTestCase {

	private User $user;

	public function addDBDataOnce(): void {
		$this->editPage( 'Growth article', 'Content of the article.' );
		$this->editPage( 'Growth redirect', '#REDIRECT [[Growth article]]' );
		$this->editPage( 'Growth double redirect', '#REDIRECT [[Growth redirect]]' );
		$this->editPage( 'Talk:Growth article', 'Content of the talk page.' );
		$this->editPage( 'Help:Growth help', 'Content of the help page.' );
		$this->editPage( 'Growth help redirect', '#REDIRECT [[Help:Growth help]]' );
		$this->editPage( 'Growth talk redirect', '#REDIRECT [[Talk:Growth article]]' );
	}

	protected function setUp(): void {
		parent::setUp();
		// Make NS_HELP a content namespace, to show that the hook stores articles only.
		$this->overrideConfigValue( MainConfigNames::ContentNamespaces, [ NS_MAIN, NS_HELP ] );
		// Register an interwiki prefix. Without it, TitleFactory parses "wikt:Dog"
		// as a local page in the article namespace.
		$this->overrideConfigValue(
			MainConfigNames::InterwikiCache,
			ClassicInterwikiLookup::buildCdbHash( [
				[
					'iw_prefix' => 'wikt',
					'iw_url' => 'https://en.wiktionary.org/wiki/$1',
					'iw_local' => 1,
				],
			] )
		);
		$this->user = $this->getMutableTestUser()->getUser();
	}

	public static function provideReturnTo(): iterable {
		yield 'article' => [ 'Growth article', [ 'Growth article' ] ];
		yield 'article, unnormalised text' => [ 'growth_article', [ 'Growth article' ] ];
		yield 'article which does not exist' => [ 'Growth missing article', null ];
		yield 'redirect to an article' => [ 'Growth redirect', [ 'Growth article' ] ];
		// RedirectLookup follows one hop only, thus the target is again a redirect.
		yield 'double redirect' => [ 'Growth double redirect', [ 'Growth redirect' ] ];
		yield 'talk page' => [ 'Talk:Growth article', null ];
		yield 'redirect to a talk page' => [ 'Growth talk redirect', null ];
		// NS_HELP is a content namespace here, but it is not the article namespace.
		yield 'content page outside the article namespace' => [ 'Help:Growth help', null ];
		yield 'redirect out of the article namespace' => [ 'Growth help redirect', null ];
		yield 'special page' => [ 'Special:RecentChanges', null ];
		yield 'invalid title' => [ '<invalid>', null ];
		yield 'empty returnto' => [ '', null ];
		yield 'interwiki link' => [ 'wikt:Dog', null ];
	}

	/**
	 * @dataProvider provideReturnTo
	 */
	public function testInterestArticleForReturnTo( string $returnTo, ?array $expectedInterest ): void {
		$this->runLocalUserCreated( [ 'returnto' => $returnTo ] );

		$this->assertSame( $expectedInterest, $this->getStoredInterestArticles() );
	}

	public function testMissingReturnToIsNotAnInterestArticle(): void {
		$this->runLocalUserCreated( [] );

		$this->assertNull( $this->getStoredInterestArticles() );
	}

	public function testMainPageIsNotAnInterestArticle(): void {
		$this->runLocalUserCreated( [ 'returnto' => Title::newMainPage()->getPrefixedText() ] );

		$this->assertNull( $this->getStoredInterestArticles() );
	}

	public function testRedirectToTheMainPageIsNotAnInterestArticle(): void {
		$this->editPage( 'Growth main page redirect', '#REDIRECT [[' . Title::newMainPage()->getPrefixedText() . ']]' );

		$this->runLocalUserCreated( [ 'returnto' => 'Growth main page redirect' ] );

		$this->assertNull( $this->getStoredInterestArticles() );
	}

	public function testAutocreatedUserGetsNoInterestArticle(): void {
		$this->runLocalUserCreated( [ 'returnto' => 'Growth article' ], autocreated: true );

		$this->assertNull( $this->getStoredInterestArticles() );
	}

	public function testUserOutsideTheTreatmentGroupGetsNoInterestArticle(): void {
		$this->runLocalUserCreated( [ 'returnto' => 'Growth article' ], isTreatment: false );

		$this->assertNull( $this->getStoredInterestArticles() );
	}

	public function testInterestArticleIsWrittenToTheDatabase(): void {
		$this->runLocalUserCreated( [ 'returnto' => 'Growth redirect' ] );
		// AuthManager calls User::saveSettings() right after the LocalUserCreated hook.
		$this->user->saveSettings();

		// Drop the in-process options cache, to read the value back from user_properties.
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->clearUserOptionsCache( $this->user );

		// The account setup dialogue reads this JSON list of prefixed titles.
		$this->assertSame(
			'["Growth article"]',
			$userOptionsManager->getOption( $this->user, AccountSetupHooks::INTEREST_ARTICLES_PROP )
		);
	}

	/**
	 * The signup redirect runs in a separate request and no longer stores the interest
	 * article. It still sends a treatment user to the homepage.
	 */
	public function testTreatmentUserIsRedirectedToTheHomepage(): void {
		RequestContext::getMain()->setUser( $this->user );
		$returnTo = 'Growth article';
		$returnToQuery = [];
		$type = 'signup';

		$this->assertTrue( $this->newAccountSetupHooks()->onPostLoginRedirect(
			$returnTo,
			$returnToQuery,
			$type
		) );

		$homepageTitleText = $this->getServiceContainer()->getSpecialPageFactory()
			->getTitleForAlias( 'Homepage' )
			->getPrefixedText();
		$this->assertSame( $homepageTitleText, $returnTo );
		$this->assertSame( 'successredirect', $type );
		$this->assertNull( $this->getStoredInterestArticles() );
	}

	/**
	 * @param array $requestValues Query values of the account creation request
	 */
	private function runLocalUserCreated(
		array $requestValues,
		bool $autocreated = false,
		bool $isTreatment = true
	): void {
		RequestContext::getMain()->setRequest( new FauxRequest( $requestValues ) );

		$this->assertTrue(
			$this->newAccountSetupHooks( $isTreatment )->onLocalUserCreated( $this->user, $autocreated )
		);
	}

	/**
	 * @return string[]|null The stored interest articles, or null if the hook stored nothing.
	 */
	private function getStoredInterestArticles(): ?array {
		$storedValue = $this->getServiceContainer()->getUserOptionsManager()->getOption(
			$this->user,
			AccountSetupHooks::INTEREST_ARTICLES_PROP
		);
		if ( $storedValue === null ) {
			return null;
		}
		return json_decode( $storedValue, true, 512, JSON_THROW_ON_ERROR );
	}

	private function newAccountSetupHooks( bool $isTreatment = true ): AccountSetupHooks {
		$featureManager = $this->createMock( FeatureManager::class );
		$featureManager->method( 'isEarlyOnboardingExperimentTreatment' )
			->willReturn( $isTreatment );

		// CentralAuth has its own redirect hook. Mock the registry, so that the result
		// does not depend on the extensions of the local installation.
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->with( 'CentralAuth' )
			->willReturn( false );

		$services = $this->getServiceContainer();
		return new AccountSetupHooks(
			$services->getSpecialPageFactory(),
			$featureManager,
			$services->getTitleFactory(),
			$extensionRegistry,
			$services->getUserIdentityUtils(),
			$services->getUserOptionsManager(),
			$services->getRedirectLookup(),
		);
	}

}
