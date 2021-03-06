<?php

use CirrusSearch\CirrusSearch;
use GrowthExperiments\AqsEditInfoService;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use GrowthExperiments\Config\WikiPageConfig;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\MentorDashboard\MenteeOverview\DatabaseMenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Mentorship\Store\MultiWriteMentorStore;
use GrowthExperiments\Mentorship\Store\PreferenceMentorStore;
use GrowthExperiments\NewcomerTasks\AddLink\AddLinkSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddLink\DbBackedLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkSubmissionRecorder;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\CirrusSearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\EventGateSearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\SearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\ServiceLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\StaticLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\SuggestionsInfo;
use GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use GrowthExperiments\WelcomeSurveyFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [

	'GrowthExperimentsAddLinkSubmissionHandler' => static function (
		MediaWikiServices $services
	): AddLinkSubmissionHandler {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new AddLinkSubmissionHandler(
			$growthServices->getLinkRecommendationHelper(),
			$growthServices->getLinkRecommendationStore(),
			$growthServices->getLinkSubmissionRecorder(),
			$services->getLinkBatchFactory(),
			$services->getTitleFactory()
		);
	},

	'GrowthExperimentsConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
	},

	'GrowthExperimentsConfigValidatorFactory' => static function (
		MediaWikiServices $services
	): ConfigValidatorFactory {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new ConfigValidatorFactory(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			$geServices->getTaskTypeHandlerRegistry()
		);
	},

	'GrowthExperimentsMultiConfig' => static function ( MediaWikiServices $services ): Config {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new GrowthExperimentsMultiConfig(
			$geServices->getWikiPageConfig(),
			GlobalVarConfig::newInstance()
		);
	},

	'GrowthExperimentsWikiPageConfig' => static function ( MediaWikiServices $services ): Config {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new WikiPageConfig(
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$services->getTitleFactory(),
			$geServices->getWikiPageConfigLoader(),
			$services->getMainConfig()->get( 'GEWikiConfigPageTitle' )
		);
	},

	'GrowthExperimentsEditInfoService' => static function ( MediaWikiServices $services ): EditInfoService {
		$project = $services->get( '_GrowthExperimentsAQSConfig' )->project;
		$editInfoService = new AqsEditInfoService( $services->getHttpRequestFactory(), $project );
		$editInfoService->setCache( ObjectCache::getLocalClusterInstance() );
		return $editInfoService;
	},

	'GrowthExperimentsExperimentUserManager' => static function (
		MediaWikiServices $services
	) : ExperimentUserManager {
		return new ExperimentUserManager(
			new ServiceOptions( [
				'GEHomepageNewAccountVariants',
				'GEHomepageDefaultVariant',
			], $services->getMainConfig() ),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsHomepageModuleRegistry' => static function (
		MediaWikiServices $services
	) : HomepageModuleRegistry {
		return new HomepageModuleRegistry( $services );
	},

	'GrowthExperimentsLinkRecommendationHelper' => static function (
		MediaWikiServices $services
	) : LinkRecommendationHelper {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();
		$cirrusSearchFactory = static function () {
			return new CirrusSearch();
		};
		return new LinkRecommendationHelper(
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getLinkRecommendationProvider(),
			$growthServices->getLinkRecommendationStore(),
			$services->getLinkBatchFactory(),
			$services->getTitleFactory(),
			$cirrusSearchFactory,
			// In developer setups, the recommendation service is usually suggestion link targets
			// from a different wiki, which might end up being red links locally. Allow these,
			// otherwise we'd get mostly failures when trying to generate new tasks.
			!$config->get( 'GEDeveloperSetup' )
		);
	},

	'GrowthExperimentsLinkRecommendationProviderUncached' => static function (
		MediaWikiServices $services
	): LinkRecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getConfig();
		$serviceUrl = $config->get( 'GELinkRecommendationServiceUrl' );
		if ( $serviceUrl ) {
			return new ServiceLinkRecommendationProvider(
				$services->getTitleFactory(),
				$services->getRevisionLookup(),
				$services->getHttpRequestFactory(),
				$config->get( 'GELinkRecommendationServiceUrl' ),
				$config->get( 'GELinkRecommendationServiceWikiIdMasquerade' ) ??
					WikiMap::getCurrentWikiId(),
				$config->get( 'GELinkRecommendationServiceAccessToken' ),
				$config->get( 'GELinkRecommendationServiceTimeout' )
			);
		} else {
			return new StaticLinkRecommendationProvider( [],
				StatusValue::newFatal( 'rawmessage', '$wgGELinkRecommendationServiceUrl not set!' ) );
		}
	},

	'GrowthExperimentsLinkRecommendationProvider' => static function (
		MediaWikiServices $services
	): LinkRecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$useFallback = $growthServices->getConfig()->get( 'GELinkRecommendationFallbackOnDBMiss' );
		$uncachedProvider = $services->get( 'GrowthExperimentsLinkRecommendationProviderUncached' );
		if ( !$uncachedProvider instanceof StaticLinkRecommendationProvider ) {
			return new DbBackedLinkRecommendationProvider(
				GrowthExperimentsServices::wrap( $services )->getLinkRecommendationStore(),
				$useFallback ? $uncachedProvider : null,
				$services->getTitleFormatter()
			);
		} else {
			return $uncachedProvider;
		}
	},

	'GrowthExperimentsLinkRecommendationStore' => static function (
		MediaWikiServices $services
	): LinkRecommendationStore {
		$loadBalancer = GrowthExperimentsServices::wrap( $services )->getLoadBalancer();
		return new LinkRecommendationStore(
			$loadBalancer->getLazyConnectionRef( DB_REPLICA ),
			$loadBalancer->getLazyConnectionRef( DB_PRIMARY ),
			$services->getTitleFactory(),
			$services->getLinkBatchFactory()
		);
	},

	'GrowthExperimentsLinkSubmissionRecorder' => static function (
		MediaWikiServices $services
	) : LinkSubmissionRecorder {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new LinkSubmissionRecorder(
			$services->getTitleParser(),
			$services->getLinkBatchFactory(),
			$growthServices->getLinkRecommendationStore()
		);
	},

	'GrowthExperimentsMenteeOverviewDataProvider' => static function (
		MediaWikiServices $services
	) : MenteeOverviewDataProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$dataProvider = new DatabaseMenteeOverviewDataProvider(
			$geServices->getMentorStore(),
			$geServices->getLoadBalancer()->getConnection( DB_REPLICA )
		);
		$dataProvider->setCache(
			ObjectCache::getLocalClusterInstance(),
			CachedBagOStuff::TTL_HOUR
		);
		return $dataProvider;
	},

	'GrowthExperimentsMenteeOverviewDataProviderUncached' => static function (
		MediaWikiServices $services
	) : MenteeOverviewDataProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new UncachedMenteeOverviewDataProvider(
			$geServices->getMentorStore(),
			$services->getChangeTagDefStore(),
			$services->getActorMigration(),
			$services->getDBLoadBalancer()->getConnection( DB_REPLICA, 'vslow' )
		);
	},

	'GrowthExperimentsMentorManager' => static function (
		MediaWikiServices $services
	) : MentorManager {
		$wikiConfig = GrowthExperimentsServices::wrap( $services )->getGrowthWikiConfig();

		$manager = new MentorPageMentorManager(
			GrowthExperimentsServices::wrap( $services )->getMentorStore(),
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getUserFactory(),
			$services->getUserNameUtils(),
			$services->getActorStore(),
			RequestContext::getMain(),
			RequestContext::getMain()->getLanguage(),
			$wikiConfig->get( 'GEHomepageMentorsList' ) ?: null,
			$wikiConfig->get( 'GEHomepageManualAssignmentMentorsList' ) ?: null,
			RequestContext::getMain()->getRequest()->wasPosted()
		);
		$manager->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $manager;
	},

	'GrowthExperimentsMentorStore' => static function ( MediaWikiServices $services ): MentorStore {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new MultiWriteMentorStore(
			(int)$geServices->getGrowthConfig()->get( 'GEMentorshipMigrationStage' ),
			$geServices->getPreferenceMentorStore(),
			$geServices->getDatabaseMentorStore(),
			defined( 'MEDIAWIKI_JOB_RUNNER' ) ||
				$geServices->getConfig()->get( 'CommandLineMode' ) ||
				RequestContext::getMain()->getRequest()->wasPosted()
		);
	},

	'GrowthExperimentsMentorStoreDatabase' => static function ( MediaWikiServices $services ): DatabaseMentorStore {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$lb = $geServices->getLoadBalancer();

		$databaseMentorStore = new DatabaseMentorStore(
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$lb->getLazyConnectionRef( DB_REPLICA ),
			$lb->getLazyConnectionRef( DB_PRIMARY ),
			defined( 'MEDIAWIKI_JOB_RUNNER' ) ||
				$geServices->getConfig()->get( 'CommandLineMode' ) ||
				RequestContext::getMain()->getRequest()->wasPosted()
		);
		$databaseMentorStore->setCache(
			ObjectCache::getLocalClusterInstance(),
			CachedBagOStuff::TTL_DAY
		);
		return $databaseMentorStore;
	},

	'GrowthExperimentsMentorStorePreference' => static function ( MediaWikiServices $services ): PreferenceMentorStore {
		return new PreferenceMentorStore(
			$services->getUserFactory(),
			$services->getUserOptionsManager(),
			defined( 'MEDIAWIKI_JOB_RUNNER' ) ||
				GrowthExperimentsServices::wrap( $services )->getConfig()->get( 'CommandLineMode' ) ||
				RequestContext::getMain()->getRequest()->wasPosted()
		);
	},

	'GrowthExperimentsNewcomerTasksConfigurationLoader' => static function (
		MediaWikiServices $services
	): ConfigurationLoader {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getConfig();

		$taskConfigTitle = $config->get( 'GENewcomerTasksConfigTitle' );
		if ( !$taskConfigTitle ) {
			return new ErrorForwardingConfigurationLoader( StatusValue::newFatal( new ApiRawMessage(
				'The ConfigurationLoader has not been configured!',
				'configurationloader-not-configured'
			) ) );
		}

		$topicType = $config->get( 'GENewcomerTasksTopicType' );
		$topicConfigTitle = null;
		if ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_ORES ) {
			$topicConfigTitle = $config->get( 'GENewcomerTasksOresTopicConfigTitle' );
		} elseif ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE ) {
			$topicConfigTitle = $config->get( 'GENewcomerTasksTopicConfigTitle' );
		}

		$configurationLoader = new PageConfigurationLoader(
			$services->getTitleFactory(),
			$growthServices->getWikiPageConfigLoader(),
			$growthServices->getNewcomerTasksConfigurationValidator(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$taskConfigTitle,
			$topicConfigTitle,
			$topicType
		);
		if ( !$config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}
		return $configurationLoader;
	},

	'GrowthExperimentsNewcomerTasksConfigurationValidator' => static function (
		MediaWikiServices $services
	): ConfigurationValidator {
		return new ConfigurationValidator(
			RequestContext::getMain(),
			Collation::singleton(),
			$services->getTitleParser()
		);
	},

	'GrowthExperimentsNewcomerTaskTrackerFactory' => static function (
		MediaWikiServices $services
	): TrackerFactory {
		return new TrackerFactory(
			$services->getMainObjectStash(),
			GrowthExperimentsServices::wrap( $services )->getNewcomerTasksConfigurationLoader(),
			$services->getTitleFactory(),
			LoggerFactory::getInstance( 'GrowthExperiments' )
		);
	},

	'GrowthExperimentsNewcomerTasksUserOptionsLookup' => static function (
		MediaWikiServices $services
	): NewcomerTasksUserOptionsLookup {
		return new NewcomerTasksUserOptionsLookup(
			GrowthExperimentsServices::wrap( $services )->getExperimentUserManager(),
			$services->getUserOptionsLookup(),
			$services->getMainConfig()
		);
	},

	'GrowthExperimentsProtectionFilter' => static function (
		MediaWikiServices $services
	): ProtectionFilter {
		return new ProtectionFilter(
			$services->getTitleFactory(),
			$services->getLinkBatchFactory()
		);
	},

	'GrowthExperimentsQuestionPosterFactory' => static function (
		MediaWikiServices $services
	): QuestionPosterFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new QuestionPosterFactory(
			$services->getWikiPageFactory(),
			$growthServices->getMentorManager(),
			$services->getPermissionManager(),
			$growthServices->getGrowthWikiConfig()->get( 'GEHelpPanelHelpDeskPostOnTop' )
		);
	},

	'GrowthExperimentsSearchIndexUpdater' => static function (
		MediaWikiServices $services
	): SearchIndexUpdater {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( $growthServices->getConfig()->get( 'GELinkRecommendationsUseEventGate' ) ) {
			/** @var EventBusFactory $eventBusFactory */
			$eventBusFactory = $services->get( 'EventBus.EventBusFactory' );
			return new EventGateSearchIndexUpdater( $eventBusFactory );
		} else {
			return new CirrusSearchIndexUpdater();
		}
	},

	'GrowthExperimentsStarredMenteesStore' => static function (
		MediaWikiServices $services
	): StarredMenteesStore {
		return new StarredMenteesStore(
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$services->getUserOptionsManager()
		);
	},

	// deprecated, use GrowthExperimentsTaskSuggesterFactory directly
	'GrowthExperimentsTaskSuggester' => static function ( MediaWikiServices $services ): TaskSuggester {
		wfDeprecated( 'GrowthExperimentsTaskSuggester service', '1.35', 'GrowthExperiments' );
		$taskSuggesterFactory = GrowthExperimentsServices::wrap( $services )->getTaskSuggesterFactory();
		return $taskSuggesterFactory->create();
	},

	'GrowthExperimentsTaskSuggesterFactory' => static function (
		MediaWikiServices $services
	): TaskSuggesterFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getConfig();

		$taskTypeHandlerRegistry = $growthServices->getTaskTypeHandlerRegistry();
		$configLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry, $configLoader );
		if ( $config->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$taskSuggesterFactory = new RemoteSearchTaskSuggesterFactory(
				$taskTypeHandlerRegistry,
				$configLoader,
				$searchStrategy,
				$growthServices->getNewcomerTasksUserOptionsLookup(),
				$services->getHttpRequestFactory(),
				$services->getTitleFactory(),
				$services->getLinkBatchFactory(),
				$config->get( 'GENewcomerTasksRemoteApiUrl' )
			);
		} else {
			$taskSuggesterFactory = new LocalSearchTaskSuggesterFactory(
				$taskTypeHandlerRegistry,
				$configLoader,
				$searchStrategy,
				$growthServices->getNewcomerTasksUserOptionsLookup(),
				$services->getSearchEngineFactory(),
				$services->getLinkBatchFactory()
			);
			$taskSuggesterFactory = new DecoratingTaskSuggesterFactory(
				$taskSuggesterFactory,
				$services->getObjectFactory(),
				[ [
					  'class' => CacheDecorator::class,
					  'args' => [
						  JobQueueGroup::singleton(),
						  $services->getMainWANObjectCache()
					  ],
				  ] ]
			);
		}
		$taskSuggesterFactory->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $taskSuggesterFactory;
	},

	'GrowthExperimentsSuggestionsInfo' => static function (
		MediaWikiServices $services
	): SuggestionsInfo {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new SuggestionsInfo(
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$growthServices->getNewcomerTasksConfigurationLoader()
		);
	},

	'GrowthExperimentsTaskTypeHandlerRegistry' => static function (
		MediaWikiServices $services
	): TaskTypeHandlerRegistry {
		$extensionConfig = GrowthExperimentsServices::wrap( $services )->getConfig();
		return new TaskTypeHandlerRegistry(
			$services->getObjectFactory(),
			$extensionConfig->get( 'GENewcomerTasksTaskTypeHandlers' )
		);
	},

	'GrowthExperimentsTipsAssembler' => static function (
		MediaWikiServices $services
	): TipsAssembler {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new TipsAssembler(
			$growthExperimentsServices->getNewcomerTasksConfigurationLoader(),
			$growthExperimentsServices->getTipNodeRenderer()
		);
	},

	'GrowthExperimentsTipNodeRenderer' => static function (
		MediaWikiServices $services
	): TipNodeRenderer {
		return new TipNodeRenderer(
			$services->getMainConfig()->get( 'ExtensionAssetsPath' )
		);
	},

	'GrowthExperimentsWelcomeSurveyFactory' => static function (
		MediaWikiServices $services
	): WelcomeSurveyFactory {
		return new WelcomeSurveyFactory(
			$services->getLanguageNameUtils(),
			$services->getUserOptionsManager()
		);
	},

	'GrowthExperimentsWikiPageConfigLoader' => static function (
		MediaWikiServices $services
	): WikiPageConfigLoader {
		$wikiPageConfigLoader = new WikiPageConfigLoader(
			GrowthExperimentsServices::wrap( $services )
				->getWikiPageConfigValidatorFactory(),
			$services->getHttpRequestFactory(),
			$services->getRevisionLookup(),
			$services->getTitleFactory()
		);

		// Cache config for a day; cache is invalidated by PageConfigurationLoader::onPageSaveComplete
		// and WikiPageConfigWriter::save when config files are changed.
		$wikiPageConfigLoader->setCache(
			new CachedBagOStuff( ObjectCache::getLocalClusterInstance() ),
			CachedBagOStuff::TTL_DAY
		);
		return $wikiPageConfigLoader;
	},

	'GrowthExperimentsWikiPageConfigWriterFactory' => static function (
		MediaWikiServices $services
	) {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new WikiPageConfigWriterFactory(
			$growthExperimentsServices->getWikiPageConfigLoader(),
			$growthExperimentsServices->getWikiPageConfigValidatorFactory(),
			$services->getWikiPageFactory(),
			$services->getTitleFactory(),
			LoggerFactory::getInstance( 'GrowthExperiments' )
		);
	},

	'_GrowthExperimentsAQSConfig' => static function ( MediaWikiServices $services ): stdClass {
		// This is not a service and doesn't quite belong here, but we need to share it with
		// Javascript code as fetching this information in bulk is not feasible, and this seems
		// the least awkward option (as opposed to creating a dedicated service just for fetching
		// configuration, or passing through all the services involved here to the ResourceLoader
		// callback). The nice long-term solution is probably to extend RL callback specification
		// syntax to allow using something like the 'services' parameter of ObjectFactory.
		$project = $services->getMainConfig()->get( 'ServerName' );
		if ( ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' ) ) {
			$project = $services->getConfigFactory()->makeConfig( 'PageViewInfo' )
				->get( 'PageViewInfoWikimediaDomain' )
				?: $project;
		}
		// MediaWikiServices insists on service factories returning an object, so wrap it into one
		return (object)[ 'project' => $project ];
	},
];
