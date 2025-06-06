<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Request\FauxRequest;
use MediaWiki\ResourceLoader as RL;
use MediaWikiIntegrationTestCase;
use StatusValue;
use stdClass;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\HomepageHooks
 * @group Database
 */
class HomepageHooksTest extends MediaWikiIntegrationTestCase {

	public function testGetTaskTypesJson() {
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

	public function testGetTaskTypesJson_error() {
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

	public function testGetAQSConfigJson() {
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
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getGrowthExperimentsCampaignConfig(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getNewcomerTasksUserOptionsLookup(),
			$growthServices->getLinkRecommendationStore(),
			$services->getSpecialPageFactory(),
			$growthServices->getNewcomerTasksChangeTagsManager(),
			$growthServices->getSuggestionsInfo(),
			$growthServices->getUserImpactLookup(),
			$growthServices->getUserImpactStore(),
			$growthServices->getGrowthExperimentsInteractionLogger(),
			$growthServices->getTaskTypeManager()
		);
	}

	public function testOnRecentChange_save() {
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
		$user = $this->getMutableTestUser()->getUser();
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

	public static function provideTestNewUserProperties() {
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
		$this->assertSame( $expectedHelpPanel, HelpPanel::shouldShowHelpPanelToUser( $user ) );
		$this->assertSame(
			$expectedMentorshipStateForUser,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getMentorManager()
				->getMentorshipStateForUser( $user )
		);
	}
}
