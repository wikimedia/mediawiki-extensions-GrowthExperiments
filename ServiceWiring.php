<?php

use GrowthExperiments\AqsEditInfoService;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\RemotePageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use MediaWiki\MediaWikiServices;

return [

	'GrowthExperimentsConfigurationLoader' => function (
		MediaWikiServices $services
	): ConfigurationLoader {
		$config = $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
		$cache = ObjectCache::getLocalClusterInstance();
		if ( $config->get( 'GENewcomerTasksRemoteConfigTitle' ) ) {
			$title = Title::newFromText( $config->get( 'GENewcomerTasksRemoteConfigTitle' ) );
			$configurationLoader = new RemotePageConfigurationLoader( $services->getHttpRequestFactory(),
				$services->getTitleFactory(), RequestContext::getMain(), $title );
			// Cache config for a minute, as a trade-off between avoiding the performance hit of
			// constant querying and making it not too hard to test changes to the config page.
			$configurationLoader->setCache( $cache, 60 );
			return $configurationLoader;
		} else {
			return new class implements ConfigurationLoader {
				/** @inheritDoc */
				public function loadTaskTypes() {
					return StatusValue::newFatal( new ApiRawMessage(
						'The ConfigurationLoader has not been configured!',
						'configurationloader-not-configured'
					) );
				}

				/** @inheritDoc */
				public function loadTemplateBlacklist() {
					return [];
				}
			};
		}
	},

	'GrowthExperimentsTaskSuggester' => function ( MediaWikiServices $services ): TaskSuggester {
		$config = $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
		/** @var ConfigurationLoader $configLoader */
		$configLoader = $services->getService( 'GrowthExperimentsConfigurationLoader' );
		$taskSuggesterFactory = new TaskSuggesterFactory( $configLoader );

		if ( $config->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			return $taskSuggesterFactory->createRemote( $services->getHttpRequestFactory(),
				$services->getTitleFactory(), $config->get( 'GENewcomerTasksRemoteApiUrl' ) );
		} else {
			return new ErrorForwardingTaskSuggester( StatusValue::newFatal( new ApiRawMessage(
				'The TaskSuggester has not been configured! See StaticTaskSuggester ' .
				'or $wgGENewcomerTasksRemoteApiUrl.', 'tasksuggester-not-configured'
			) ) );
		}
	},

	'GrowthExperimentsEditInfoService' => function ( MediaWikiServices $services ): EditInfoService {
		$editInfoService = new AqsEditInfoService( $services->getHttpRequestFactory(),
			$services->getMainConfig()->get( 'ServerName' ) );
		$editInfoService->setCache( ObjectCache::getLocalClusterInstance() );
		return $editInfoService;
	},

];
