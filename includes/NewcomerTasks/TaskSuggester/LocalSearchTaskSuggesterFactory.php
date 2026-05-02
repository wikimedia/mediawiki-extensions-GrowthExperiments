<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Search\SearchEngineFactory;
use MediaWiki\Status\StatusFormatter;
use StatusValue;
use Wikimedia\Stats\StatsFactory;

/**
 * Factory for LocalSearchTaskSuggester.
 */
class LocalSearchTaskSuggesterFactory extends SearchTaskSuggesterFactory {

	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		private SearchEngineFactory $searchEngineFactory,
		LinkBatchFactory $linkBatchFactory,
		private StatsFactory $statsFactory,
		StatusFormatter $statusFormatter,
		private ITopicRegistry $topicRegistry
	) {
		parent::__construct(
			$taskTypeHandlerRegistry,
			$configurationLoader,
			$searchStrategy,
			$newcomerTasksUserOptionsLookup,
			$linkBatchFactory,
			$statusFormatter
		);
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
			$this->statusFormatter,
			$taskTypes,
			$topics,
			$this->statsFactory
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

}
