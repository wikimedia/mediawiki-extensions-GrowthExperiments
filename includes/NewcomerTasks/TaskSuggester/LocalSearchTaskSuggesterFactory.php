<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use MediaWiki\Cache\LinkBatchFactory;
use SearchEngineFactory;
use StatusValue;
use Wikimedia\Stats\IBufferingStatsdDataFactory;
use Wikimedia\Stats\StatsFactory;

/**
 * Factory for LocalSearchTaskSuggester.
 */
class LocalSearchTaskSuggesterFactory extends SearchTaskSuggesterFactory {

	/** @var SearchEngineFactory */
	private $searchEngineFactory;

	private StatsFactory $statsFactory;
	private IBufferingStatsdDataFactory $statsdDataFactory;
	private ITopicRegistry $topicRegistry;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param SearchEngineFactory $searchEngineFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param StatsFactory $statsFactory
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param ITopicRegistry $topicRegistry
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		SearchEngineFactory $searchEngineFactory,
		LinkBatchFactory $linkBatchFactory,
		StatsFactory $statsFactory,
		IBufferingStatsdDataFactory $statsdDataFactory,
		ITopicRegistry $topicRegistry
	) {
		parent::__construct(
			$taskTypeHandlerRegistry,
			$configurationLoader,
			$searchStrategy,
			$newcomerTasksUserOptionsLookup,
			$linkBatchFactory
		);
		$this->searchEngineFactory = $searchEngineFactory;
		$this->statsFactory = $statsFactory;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->topicRegistry = $topicRegistry;
	}

	/**
	 * @param ConfigurationLoader|null $customConfigurationLoader
	 * @return LocalSearchTaskSuggester|ErrorForwardingTaskSuggester
	 */
	public function create( ?ConfigurationLoader $customConfigurationLoader = null ) {
		$configurationLoader = $customConfigurationLoader ?? $this->configurationLoader;
		$taskTypes = $configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return $this->createError( $taskTypes );
		}
		if ( $configurationLoader instanceof TopicDecorator ) {
			$topics = $configurationLoader->getTopics();
		} else {
			$topics = $this->topicRegistry->getTopics();
		}
		$suggester = new LocalSearchTaskSuggester(
			$this->taskTypeHandlerRegistry,
			$this->searchEngineFactory,
			$this->searchStrategy,
			$this->newcomerTasksUserOptionsLookup,
			$this->linkBatchFactory,
			$taskTypes,
			$topics,
			$this->statsFactory,
			$this->statsdDataFactory
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

}
