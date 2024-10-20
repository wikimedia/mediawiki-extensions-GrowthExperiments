<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Cache\LinkBatchFactory;
use SearchEngineFactory;
use StatusValue;
use Wikimedia\Stats\IBufferingStatsdDataFactory;

/**
 * Factory for LocalSearchTaskSuggester.
 */
class LocalSearchTaskSuggesterFactory extends SearchTaskSuggesterFactory {

	/** @var SearchEngineFactory */
	private $searchEngineFactory;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param SearchEngineFactory $searchEngineFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		SearchEngineFactory $searchEngineFactory,
		LinkBatchFactory $linkBatchFactory,
		IBufferingStatsdDataFactory $statsdDataFactory
	) {
		parent::__construct(
			$taskTypeHandlerRegistry,
			$configurationLoader,
			$searchStrategy,
			$newcomerTasksUserOptionsLookup,
			$linkBatchFactory
		);
		$this->searchEngineFactory = $searchEngineFactory;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	/**
	 * @param ConfigurationLoader|null $customConfigurationLoader
	 * @return LocalSearchTaskSuggester|ErrorForwardingTaskSuggester
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
		$suggester = new LocalSearchTaskSuggester(
			$this->taskTypeHandlerRegistry,
			$this->searchEngineFactory,
			$this->searchStrategy,
			$this->newcomerTasksUserOptionsLookup,
			$this->linkBatchFactory,
			$taskTypes,
			$topics,
			$this->statsdDataFactory
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

}
