<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchContext;
use GrowthExperiments\ErrorException;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\UnderlinkedFunctionScoreBuilder;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\Tests\Helpers\HomepageHooksHelpers;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Request\FauxRequest;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use StatusValue;
use stdClass;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\HomepageHooks
 * @group Database
 */
class HomepageHooksTest extends MediaWikiIntegrationTestCase {
	use HomepageHooksHelpers;

	public function testGetTaskTypesJson(): void {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->onlyMethods( [ 'loadTaskTypes' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
			->willReturn( [
				new TaskType( 'tt1', TaskType::DIFFICULTY_EASY ),
				new TaskType( 'tt2', TaskType::DIFFICULTY_EASY ),
			] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$context = new RL\Context( $this->getServiceContainer()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ 'tt1', 'tt2' ], array_keys( $configData ) );
	}

	/**
	 * @throws ErrorException
	 */
	public function testGetTaskTypesJsonParsesNonbreakingSpaceCorrectly(): void {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->onlyMethods( [ 'loadTaskTypes' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
		->willReturn( [
			new LinkRecommendationTaskType( 'tt1', TaskType::DIFFICULTY_EASY,
				[ 'maximumEditsTaskIsAvailable' => '5' ] ),
			new LinkRecommendationTaskType( 'tt2', TaskType::DIFFICULTY_EASY,
				[ 'maximumEditsTaskIsAvailable' => '5' ] ),
		] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader',
			$configurationLoader );

		$request = new FauxRequest();
		$request->setVal( 'lang', 'fr' );
		$rlContext = new RL\Context(
			$this->getServiceContainer()->getResourceLoader(),
			$request,
		);

		$configData = HomepageHooks::getTaskTypesJson( $rlContext );
		$this->assertStringNotContainsString( '&#160', $configData["tt1"]["extraMessages"]["unavailable"] );
	}

	public function testGetTaskTypesJson_error(): void {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->onlyMethods( [ 'loadTaskTypes' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
			->willReturn( StatusValue::newFatal( 'foo' ) );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$context = new RL\Context( $this->getServiceContainer()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ '_error' => '(foo)' ], $configData );
	}

	public function testGetAQSConfigJson(): void {
		$config = HomepageHooks::getAQSConfigJson();
		$this->assertInstanceOf( stdClass::class, $config );
		$this->assertObjectHasProperty( 'project', $config );
	}

	private function getHomepageHooks(): HomepageHooks {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new HomepageHooks(
			$services->getMainConfig(),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup(),
			$services->getUserIdentityUtils(),
			$services->getNamespaceInfo(),
			$services->getTitleFactory(),
			$services->getStatsFactory(),
			$services->getJobQueueGroup(),
			$services->getFormatterFactory(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getGrowthExperimentsCampaignConfig(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getTaskSetFiltersFactory(),
			$services->getSpecialPageFactory(),
			$growthServices->getNewcomerTasksChangeTagsManager(),
			$growthServices->getUserImpactLookup(),
			$growthServices->getUserImpactStore(),
			$growthServices->getTaskTypeManager(),
			$growthServices->getLevelingUpManager(),
			$growthServices->getFeatureManager()
		);
	}

	/**
	 * A user that has Home gets a tab to Special:Home, and not to
	 * Special:Homepage.
	 */
	public function testTheDashboardTabPointsAtHomeForAHomeUser() {
		$this->markTestSkippedIfExtensionNotLoaded( 'PersonalDashboard' );
		$this->overrideConfigValue( 'GEHomeEnabled', true );
		$user = $this->newUserWithHomepagePreference();

		$links = $this->runNavigationHook( $user, $user->getUserPage() );
		$tab = $links['associated-pages']['homepage'];

		$this->assertSame(
			SpecialPage::getTitleFor( 'Home' )->getLocalURL( wfArrayToCgi( [
				'source' => 'userpagetab',
				'namespace' => NS_USER,
			] ) ),
			$tab['href'],
			'The tab points at Special:Home, and it keeps the source of the Homepage tab'
		);
		// The context language is qqx, so a message shows as its key.
		$this->assertSame( '(growthexperiments-home-tab)', $tab['text'] );
	}

	/**
	 * A wiki without Home keeps the Special:Homepage tab.
	 */
	public function testTheDashboardTabPointsAtHomepageWithoutHome() {
		$this->overrideConfigValue( 'GEHomeEnabled', false );
		$user = $this->newUserWithHomepagePreference();

		$links = $this->runNavigationHook( $user, $user->getUserPage() );
		$tab = $links['associated-pages']['homepage'];

		$this->assertSame(
			SpecialPage::getTitleFor( 'Homepage' )->getLocalURL( wfArrayToCgi( [
				'source' => 'userpagetab',
				'namespace' => NS_USER,
			] ) ),
			$tab['href']
		);
		$this->assertSame( '(growthexperiments-homepage-tab)', $tab['text'] );
	}

	/**
	 * Special:Home carries the same tabs as Special:Homepage, and its own tab
	 * is the selected one.
	 */
	public function testSpecialHomeGetsTheTabs() {
		$this->markTestSkippedIfExtensionNotLoaded( 'PersonalDashboard' );
		$this->overrideConfigValue( 'GEHomeEnabled', true );
		$user = $this->newUserWithHomepagePreference();
		// A tab of a page that does not exist points at the edit form. Create
		// the two pages, so the tabs show the plain page URL.
		$this->insertPage( $user->getUserPage() );
		$this->insertPage( $user->getTalkPage() );

		$links = $this->runNavigationHook( $user, SpecialPage::getTitleFor( 'Home' ) );
		$tabs = $links['associated-pages'];

		$this->assertSame(
			[ 'homepage', 'user', 'user_talk' ],
			array_keys( $tabs ),
			'Special:Home shows the dashboard, user and talk tabs'
		);
		$this->assertStringContainsString( 'selected', $tabs['homepage']['class'] );
		$this->assertSame(
			SpecialPage::getTitleFor( 'Home' )->getLocalURL(),
			$tabs['homepage']['href'],
			'The tab of the page the user looks at carries no source'
		);
		$this->assertSame( $user->getUserPage()->getLocalURL(), $tabs['user']['href'] );
		$this->assertSame( $user->getTalkPage()->getLocalURL(), $tabs['user_talk']['href'] );
	}

	private function newUserWithHomepagePreference(): User {
		$user = $this->getMutableTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$userOptionsManager->saveOptions( $user );
		return $user;
	}

	/**
	 * Run the navigation hook for a user on a page, and give back the links.
	 *
	 * @param User $user
	 * @param Title $title
	 * @return array
	 */
	private function runNavigationHook( User $user, Title $title ): array {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setTitle( $title );
		$context->setLanguage( 'qqx' );

		$skin = new SkinTemplate( [ 'name' => 'test' ] );
		$skin->setContext( $context );

		$links = [
			'associated-pages' => [],
			'user-page' => [],
			'user-menu' => [],
		];
		$this->getHomepageHooks()->onSkinTemplateNavigation__Universal( $skin, $links );
		return $links;
	}

	public function testOnRecentChange_save(): void {
		// FIXME: These tests should cover a success case as well, and should
		// use a data provider for the test cases.
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', true );
		$homepageHooks = $this->getHomepageHooks();
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		TestingAccessWrapper::newFromObject( $homepageHooks )
			->userOptionsLookup = $services->getUserOptionsLookup();
		TestingAccessWrapper::newFromObject( $homepageHooks )
			->taskTypeHandlerRegistry = $growthServices->getTaskTypeHandlerRegistry();

		$request = new FauxRequest( [ 'taskType' => 'copyedit' ], true );
		$this->setRequest( $request );
		// 1. Anon users don't get to add newcomer task tags.
		$recentChange = new RecentChange();
		$recentChange->setAttribs( [
			'tags' => [ 'foo' ],
			'rc_user' => 0,
			'rc_user_text' => 'Anonymous',
		] );
		$homepageHooks->onRecentChange_save( $recentChange );
		$this->assertArrayEquals( [ 'foo' ], $recentChange->getAttribute( 'tags' ) );

		// Auth user tests with users who have activated Suggested Edits.
		$user = $this->getMutableTestUser()->getUserIdentity();
		$services->getUserOptionsManager()->setOption( $user, SuggestedEdits::ACTIVATED_PREF, 1 );
		$services->getUserOptionsManager()->saveOptions( $user );

		// 2. No tags added without plugins.
		$recentChange = new RecentChange();
		$recentChange->setAttribs( [
			'tags' => [ 'foo' ],
			'rc_user' => $user->getId(),
		] );
		$homepageHooks->onRecentChange_save( $recentChange );
		$this->assertArrayEquals( [ 'foo' ], $recentChange->getAttribute( 'tags' ) );
	}

	public static function provideTestNewUserProperties(): iterable {
		return [
			[ true, true, IMentorManager::MENTORSHIP_ENABLED, [
				'GEMentorshipNewAccountEnablePercentage' => 100,
			] ],
			[ true, true, IMentorManager::MENTORSHIP_DISABLED, [
				'GEMentorshipNewAccountEnablePercentage' => 0,
			] ],
		];
	}

	/**
	 * @dataProvider provideTestNewUserProperties
	 */
	public function testNewUserProperties(
		bool $expectedHomepage, bool $expectedHelpPanel, int $expectedMentorshipStateForUser,
		array $configOverrides
	) {
		$this->overrideConfigValues( $configOverrides );

		$user = $this->getMutableTestUser()->getUser();
		// TODO: Remove the hook runner once the logic does not involve setting options on
		// registration (T383700).
		$runner = new HookRunner( $this->getServiceContainer()->getHookContainer() );
		$runner->onLocalUserCreated( $user, false );

		$this->assertSame( $expectedHomepage, HomepageHooks::isHomepageEnabled( $user ) );
		$this->assertSame(
			$expectedHelpPanel,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getHelpPanel()->shouldShowHelpPanelToUser( $user )
		);
		$this->assertSame(
			$expectedMentorshipStateForUser,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getMentorManager()
				->getMentorshipStateForUser( $user )
		);
	}

	public function testOnCirrusSearchScoreBuilder() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$taskTypeMock = $this->createMock( LinkRecommendationTaskType::class );
		$taskTypeMock->method( 'getUnderlinkedWeight' )->willReturn( 1.0 );
		$taskTypeMock->method( 'getUnderlinkedMinLength' )->willReturn( 2 );
		$taskTypes = [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => $taskTypeMock,
		];

		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->method( 'getTaskTypes' )->willReturn( $taskTypes );
		$homepageHooks = $this->getHomepageHooksMock(
			null, null, null, null,
			$configurationLoaderMock
		);

		$searchContextMock = $this->createNoOpMock( SearchContext::class );
		$function = [ 'type' => UnderlinkedFunctionScoreBuilder::TYPE ];

		$builder = null;
		$retval = $homepageHooks->onCirrusSearchScoreBuilder( $function, $searchContextMock, $builder );

		$this->assertFalse( $retval );
		$this->assertInstanceOf( UnderlinkedFunctionScoreBuilder::class, $builder );
	}

	public function testOnCirrusSearchScoreBuilderDisabled() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->method( 'getTaskTypes' )->willReturn( [] );
		$homepageHooks = $this->getHomepageHooksMock(
			null, null, null, null,
			$configurationLoaderMock
		);

		$searchContextMock = $this->createNoOpMock( SearchContext::class );
		$function = [ 'type' => UnderlinkedFunctionScoreBuilder::TYPE ];

		$builder = null;
		$retval = $homepageHooks->onCirrusSearchScoreBuilder( $function, $searchContextMock, $builder );

		$this->assertFalse( $retval );
		$this->assertNotNull( $builder );
	}

	/**
	 * When Suggested Edits is disabled, onCirrusSearchAddQueryFeatures
	 * must NOT call ConfigurationLoader::getTaskTypes().
	 */
	public function testOnCirrusSearchAddQueryFeaturesFeatureDisabled() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', false );
		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->expects( $this->never() )
			->method( 'getTaskTypes' );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoaderMock );
		$searchConfig = new HashSearchConfig( [] );
		$extraFeatures = [];
		HomepageHooks::onCirrusSearchAddQueryFeatures( $searchConfig, $extraFeatures );
		$this->assertSame( [], $extraFeatures );
	}

}
