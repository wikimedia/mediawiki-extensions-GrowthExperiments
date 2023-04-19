<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Title\TitleFactory;
use StatusValue;

/**
 * Factory for RemoteSearchTaskSuggester.
 */
class RemoteSearchTaskSuggesterFactory extends SearchTaskSuggesterFactory {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var string */
	private $apiUrl;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param string $apiUrl Base URL of the remote API (ending with 'api.php').
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory,
		string $apiUrl
	) {
		parent::__construct(
			$taskTypeHandlerRegistry,
			$configurationLoader,
			$searchStrategy,
			$newcomerTasksUserOptionsLookup,
			$linkBatchFactory
		);
		$this->requestFactory = $requestFactory;
		$this->titleFactory = $titleFactory;
		$this->apiUrl = $apiUrl;
	}

	/**
	 * @param ConfigurationLoader|null $customConfigurationLoader
	 * @return RemoteSearchTaskSuggester|ErrorForwardingTaskSuggester
	 */
	public function create( ConfigurationLoader $customConfigurationLoader = null ) {
		$configurationLoader = $customConfigurationLoader ?? $this->configurationLoader;
		$taskTypes = $configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return $this->createError( $taskTypes );
		}
		$topics = $configurationLoader->loadTopics();
		if ( $topics instanceof StatusValue ) {
			return $this->createError( $topics );
		}
		$suggester = new RemoteSearchTaskSuggester(
			$this->taskTypeHandlerRegistry,
			$this->searchStrategy,
			$this->newcomerTasksUserOptionsLookup,
			$this->linkBatchFactory,
			$this->requestFactory,
			$this->titleFactory,
			$this->apiUrl,
			$taskTypes,
			$topics
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

}
