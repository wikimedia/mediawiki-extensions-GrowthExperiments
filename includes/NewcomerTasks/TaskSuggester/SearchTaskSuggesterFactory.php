<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Page\LinkBatchFactory;
use Psr\Log\NullLogger;

abstract class SearchTaskSuggesterFactory extends TaskSuggesterFactory {

	/** @var TaskTypeHandlerRegistry */
	protected $taskTypeHandlerRegistry;

	/** @var ConfigurationLoader */
	protected $configurationLoader;

	/** @var SearchStrategy */
	protected $searchStrategy;

	/** @var NewcomerTasksUserOptionsLookup */
	protected $newcomerTasksUserOptionsLookup;

	/** @var LinkBatchFactory */
	protected $linkBatchFactory;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->configurationLoader = $configurationLoader;
		$this->searchStrategy = $searchStrategy;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->logger = new NullLogger();
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
	}

}
