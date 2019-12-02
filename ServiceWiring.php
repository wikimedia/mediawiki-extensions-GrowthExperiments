<?php

use GrowthExperiments\AqsEditInfoService;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use MediaWiki\MediaWikiServices;

return [

	'GrowthExperimentsConfigurationLoader' => function (
		MediaWikiServices $services
	): ConfigurationLoader {
		$config = $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
		$cache = ObjectCache::getLocalClusterInstance();
		$configTitle = Title::newFromText(
			$config->get( 'GENewcomerTasksConfigTitle' )
			?: $config->get( 'GENewcomerTasksRemoteConfigTitle' )
		);
		if ( $configTitle ) {
			$configurationLoader = new PageConfigurationLoader(
				$services->getHttpRequestFactory(),
				$services->getTitleFactory(),
				RequestContext::getMain(),
				$configTitle
			);
			if ( !$configTitle->isExternal() ) {
				$configurationLoader->setWikiPage(
					WikiPage::factory( $services->getTitleFactory()->newFromLinkTarget( $configTitle ) )
				);
			}
			// Cache config for a minute, as a trade-off between avoiding the performance hit of
			// constant querying and making it not too hard to test changes to the config page.
			$configurationLoader->setCache( $cache, 60 );
			return $configurationLoader;
		} else {
			return new ErrorForwardingConfigurationLoader( StatusValue::newFatal( new ApiRawMessage(
				'The ConfigurationLoader has not been configured!',
				'configurationloader-not-configured'
			) ) );
		}
	},

	'GrowthExperimentsTaskSuggester' => function ( MediaWikiServices $services ): TaskSuggester {
		$config = $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
		/** @var ConfigurationLoader $configLoader */
		$configLoader = $services->getService( 'GrowthExperimentsConfigurationLoader' );
		$taskSuggesterFactory = new TaskSuggesterFactory( $configLoader );

		$dbr = $services->getDBLoadBalancer()->getLazyConnectionRef( DB_REPLICA );
		$templateProvider = new TemplateProvider( $services->getTitleFactory(), $dbr );
		if ( $config->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			return $taskSuggesterFactory->createRemote( $templateProvider,
				$services->getHttpRequestFactory(), $services->getTitleFactory(),
				$config->get( 'GENewcomerTasksRemoteApiUrl' ) );
		} else {
			return $taskSuggesterFactory->createLocal(
				$services->getSearchEngineFactory(),
				$templateProvider
			);
		}
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

];
