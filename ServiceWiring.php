<?php

use CirrusSearch\CirrusSearch;
use GrowthExperiments\AqsEditInfoService;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\WikiPageConfig;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigValidation;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Mentorship\Store\MultiWriteMentorStore;
use GrowthExperiments\Mentorship\Store\PreferenceMentorStore;
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
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [

	'GrowthExperimentsConfig' => function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
	},

	'GrowthExperimentsMultiConfig' => function ( MediaWikiServices $services ): Config {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new GrowthExperimentsMultiConfig(
			$geServices->getWikiPageConfig(),
			GlobalVarConfig::newInstance()
		);
	},

	'GrowthExperimentsWikiPageConfig' => function ( MediaWikiServices $services ): Config {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new WikiPageConfig(
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$services->getTitleFactory(),
			$geServices->getWikiPageConfigLoader(),
			$services->getMainConfig()->get( 'GEWikiConfigPageTitle' )
		);
	},

	'GrowthExperimentsEditInfoService' => function ( MediaWikiServices $services ): EditInfoService {
		$project = $services->get( '_GrowthExperimentsAQSConfig' )->project;
		$editInfoService = new AqsEditInfoService( $services->getHttpRequestFactory(), $project );
		$editInfoService->setCache( ObjectCache::getLocalClusterInstance() );
		return $editInfoService;
	},

	'GrowthExperimentsExperimentUserManager' => function (
		MediaWikiServices $services
	) : ExperimentUserManager {
		return new ExperimentUserManager(
			new ServiceOptions( [ 'GEHomepageDefaultVariant' ], $services->getMainConfig() ),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsHomepageModuleRegistry' => function (
		MediaWikiServices $services
	) : HomepageModuleRegistry {
		return new HomepageModuleRegistry( $services );
	},

	'GrowthExperimentsLinkRecommendationHelper' => function (
		MediaWikiServices $services
	) : LinkRecommendationHelper {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();
		$cirrusSearchFactory = function () {
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

	'GrowthExperimentsLinkRecommendationProviderUncached' => function (
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

	'GrowthExperimentsLinkRecommendationProvider' => function (
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

	'GrowthExperimentsLinkRecommendationStore' => function (
		MediaWikiServices $services
	): LinkRecommendationStore {
		$loadBalancer = GrowthExperimentsServices::wrap( $services )->getLoadBalancer();
		return new LinkRecommendationStore(
			$loadBalancer->getLazyConnectionRef( DB_REPLICA ),
			$loadBalancer->getLazyConnectionRef( DB_MASTER ),
			$services->getTitleFactory(),
			$services->getLinkBatchFactory()
		);
	},

	'GrowthExperimentsLinkSubmissionRecorder' => function (
		MediaWikiServices $services
	) : LinkSubmissionRecorder {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new LinkSubmissionRecorder(
			$services->getTitleParser(),
			$services->getLinkBatchFactory(),
			$growthServices->getLinkRecommendationStore()
		);
	},

	'GrowthExperimentsMentorManager' => function (
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

	'GrowthExperimentsMentorStore' => function ( MediaWikiServices $services ): MentorStore {
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

	'GrowthExperimentsMentorStoreDatabase' => function ( MediaWikiServices $services ): DatabaseMentorStore {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$lb = $geServices->getLoadBalancer();

		$databaseMentorStore = new DatabaseMentorStore(
			$services->getUserFactory(),
			$lb->getLazyConnectionRef( DB_REPLICA ),
			$lb->getLazyConnectionRef( DB_MASTER ),
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

	'GrowthExperimentsMentorStorePreference' => function ( MediaWikiServices $services ): PreferenceMentorStore {
		return new PreferenceMentorStore(
			$services->getUserFactory(),
			$services->getUserOptionsManager(),
			defined( 'MEDIAWIKI_JOB_RUNNER' ) ||
				GrowthExperimentsServices::wrap( $services )->getConfig()->get( 'CommandLineMode' ) ||
				RequestContext::getMain()->getRequest()->wasPosted()
		);
	},

	'GrowthExperimentsNewcomerTasksConfigurationLoader' => function (
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

	'GrowthExperimentsNewcomerTasksConfigurationValidator' => function (
		MediaWikiServices $services
	): ConfigurationValidator {
		return new ConfigurationValidator(
			RequestContext::getMain(),
			Collation::singleton(),
			$services->getTitleParser()
		);
	},

	'GrowthExperimentsNewcomerTaskTrackerFactory' => function (
		MediaWikiServices $services
	): TrackerFactory {
		return new TrackerFactory(
			$services->getMainObjectStash(),
			GrowthExperimentsServices::wrap( $services )->getNewcomerTasksConfigurationLoader(),
			$services->getTitleFactory(),
			LoggerFactory::getInstance( 'GrowthExperiments' )
		);
	},

	'GrowthExperimentsNewcomerTasksUserOptionsLookup' => function (
		MediaWikiServices $services
	): NewcomerTasksUserOptionsLookup {
		return new NewcomerTasksUserOptionsLookup(
			$services->getUserOptionsLookup(),
			$services->getMainConfig()
		);
	},

	'GrowthExperimentsProtectionFilter' => function (
		MediaWikiServices $services
	): ProtectionFilter {
		return new ProtectionFilter(
			$services->getTitleFactory(),
			$services->getLinkBatchFactory()
		);
	},

	'GrowthExperimentsQuestionPosterFactory' => function (
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

	'GrowthExperimentsSearchIndexUpdater' => function (
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

	// deprecated, use GrowthExperimentsTaskSuggesterFactory directly
	'GrowthExperimentsTaskSuggester' => function ( MediaWikiServices $services ): TaskSuggester {
		wfDeprecated( 'GrowthExperimentsTaskSuggester service', '1.35', 'GrowthExperiments' );
		$taskSuggesterFactory = GrowthExperimentsServices::wrap( $services )->getTaskSuggesterFactory();
		return $taskSuggesterFactory->create();
	},

	'GrowthExperimentsTaskSuggesterFactory' => function (
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

	'GrowthExperimentsSuggestionsInfo' => function (
		MediaWikiServices $services
	): SuggestionsInfo {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new SuggestionsInfo(
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$growthServices->getNewcomerTasksConfigurationLoader()
		);
	},

	'GrowthExperimentsTaskTypeHandlerRegistry' => function (
		MediaWikiServices $services
	): TaskTypeHandlerRegistry {
		$extensionConfig = GrowthExperimentsServices::wrap( $services )->getConfig();
		return new TaskTypeHandlerRegistry(
			$services->getObjectFactory(),
			$extensionConfig->get( 'GENewcomerTasksTaskTypeHandlers' )
		);
	},

	'GrowthExperimentsTipsAssembler' => function (
		MediaWikiServices $services
	): TipsAssembler {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new TipsAssembler(
			$growthExperimentsServices->getNewcomerTasksConfigurationLoader(),
			$growthExperimentsServices->getTipNodeRenderer()
		);
	},

	'GrowthExperimentsTipNodeRenderer' => function (
		MediaWikiServices $services
	): TipNodeRenderer {
		return new TipNodeRenderer(
			$services->getMainConfig()->get( 'ExtensionAssetsPath' )
		);
	},

	'GrowthExperimentsWikiPageConfigLoader' => function (
		MediaWikiServices $services
	): WikiPageConfigLoader {
		$wikiPageConfigLoader = new WikiPageConfigLoader(
			new WikiPageConfigValidation(),
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

	'GrowthExperimentsWikiPageConfigWriterFactory' => function (
		MediaWikiServices $services
	) {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new WikiPageConfigWriterFactory(
			$growthExperimentsServices->getWikiPageConfigLoader(),
			$services->getWikiPageFactory(),
			$services->getTitleFactory(),
			LoggerFactory::getInstance( 'GrowthExperiments' )
		);
	},

	'_GrowthExperimentsAQSConfig' => function ( MediaWikiServices $services ): stdClass {
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
