<?php

use GrowthExperiments\AqsEditInfoService;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [

	'GrowthExperimentsConfigurationLoader' => function (
		MediaWikiServices $services
	): ConfigurationLoader {
		$config = $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
		$cache = new CachedBagOStuff( ObjectCache::getLocalClusterInstance() );

		$taskConfigTitle = Title::newFromText( $config->get( 'GENewcomerTasksConfigTitle' ) );
		if ( !$taskConfigTitle ) {
			return new ErrorForwardingConfigurationLoader( StatusValue::newFatal( new ApiRawMessage(
				'The ConfigurationLoader has not been configured!',
				'configurationloader-not-configured'
			) ) );
		}

		$topicType = $config->get( 'GENewcomerTasksTopicType' );
		$topicConfigTitle = null;
		if ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_ORES ) {
			$topicConfigTitle = Title::newFromText( $config->get( 'GENewcomerTasksOresTopicConfigTitle' ) );
		} elseif ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE ) {
			$topicConfigTitle = Title::newFromText( $config->get( 'GENewcomerTasksTopicConfigTitle' ) );
		}

		$pageLoader = new PageLoader(
			$services->getHttpRequestFactory(),
			$services->getRevisionLookup(),
			$services->getTitleFactory()
		);
		// Cache config for a minute, as a trade-off between avoiding the performance hit of
		// constant querying and making it not too hard to test changes to the config page.
		$pageLoader->setCache( $cache, 60 );

		$configurationLoader = new PageConfigurationLoader(
			RequestContext::getMain(),
			$pageLoader,
			Collation::singleton(),
			$taskConfigTitle,
			$topicConfigTitle,
			$topicType
		);
		return $configurationLoader;
	},

	'GrowthExperimentsTaskSuggesterFactory' => function (
		MediaWikiServices $services
	): TaskSuggesterFactory {
		$config = $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );

		/** @var ConfigurationLoader $configLoader */
		$configLoader = $services->getService( 'GrowthExperimentsConfigurationLoader' );
		$searchStrategy = new SearchStrategy();
		$dbr = $services->getDBLoadBalancer()->getLazyConnectionRef( DB_REPLICA );
		$templateProvider = new TemplateProvider( $services->getTitleFactory(), $dbr );
		if ( $config->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$taskSuggesterFactory = new RemoteSearchTaskSuggesterFactory(
				$configLoader,
				$searchStrategy,
				$templateProvider,
				$services->getHttpRequestFactory(),
				$services->getTitleFactory(),
				$config->get( 'GENewcomerTasksRemoteApiUrl' )
			);
		} else {
			$taskSuggesterFactory = new LocalSearchTaskSuggesterFactory(
				$configLoader,
				$searchStrategy,
				$templateProvider,
				$services->getSearchEngineFactory()
			);
		}
		$taskSuggesterFactory->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $taskSuggesterFactory;
	},

	// deprecated, use GrowthExperimentsTaskSuggesterFactory directly
	'GrowthExperimentsTaskSuggester' => function ( MediaWikiServices $services ): TaskSuggester {
		wfDeprecated( 'GrowthExperimentsTaskSuggester service', '1.35', 'GrowthExperiments' );
		/** @var TaskSuggesterFactory $taskSuggesterFactory */
		$taskSuggesterFactory = $services->get( 'GrowthExperimentsTaskSuggesterFactory' );
		return $taskSuggesterFactory->create();
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

	'GrowthExperimentsEditInfoService' => function ( MediaWikiServices $services ): EditInfoService {
		$project = $services->get( '_GrowthExperimentsAQSConfig' )->project;
		$editInfoService = new AqsEditInfoService( $services->getHttpRequestFactory(), $project );
		$editInfoService->setCache( ObjectCache::getLocalClusterInstance() );
		return $editInfoService;
	},

	'GrowthExperimentsNewcomerTaskTrackerFactory' => function (
		MediaWikiServices $services
	): TrackerFactory {
		return new TrackerFactory(
			$services->getMainObjectStash(),
			$services->getTitleFactory(),
			LoggerFactory::getInstance( 'GrowthExperiments' )
		);
	},

	'GrowthExperimentsTipsAssembler' => function (
		MediaWikiServices $services
	): TipsAssembler {
		return new TipsAssembler(
			$services->get( 'GrowthExperimentsConfigurationLoader' ),
			$services->get( 'GrowthExperimentsTipNodeRenderer' )
		);
	},

	'GrowthExperimentsTipNodeRenderer' => function (
		MediaWikiServices $services
	): TipNodeRenderer {
		return new TipNodeRenderer(
			$services->getMainConfig()->get( 'ExtensionAssetsPath' )
		);
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

];
