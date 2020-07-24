<?php

use GrowthExperiments\AqsEditInfoService;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
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
		$config = GrowthExperimentsServices::wrap( $services )->getConfig();
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

	'GrowthExperimentsMentorManager' => function (
		MediaWikiServices $services
	) : MentorManager {
		$config = GrowthExperimentsServices::wrap( $services )->getConfig();
		$manager = new MentorPageMentorManager(
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getUserFactory(),
			$services->getUserOptionsManager(),
			$services->getUserNameUtils(),
			RequestContext::getMain(),
			RequestContext::getMain()->getLanguage(),
			$config->get( 'GEHomepageMentorsList' ) ?? ''
		);
		$manager->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $manager;
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

	'GrowthExperimentsQuestionPosterFactory' => function (
		MediaWikiServices $services
	): QuestionPosterFactory {
		$mentorManager = GrowthExperimentsServices::wrap( $services )->getMentorManager();
		return new QuestionPosterFactory( $mentorManager );
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
		$config = GrowthExperimentsServices::wrap( $services )->getConfig();

		$configLoader = GrowthExperimentsServices::wrap( $services )->getConfigurationLoader();
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

	'GrowthExperimentsTipsAssembler' => function (
		MediaWikiServices $services
	): TipsAssembler {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new TipsAssembler(
			$growthExperimentsServices->getConfigurationLoader(),
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
