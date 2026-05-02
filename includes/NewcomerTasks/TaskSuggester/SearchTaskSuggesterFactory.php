<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Status\StatusFormatter;
use Psr\Log\NullLogger;

abstract class SearchTaskSuggesterFactory extends TaskSuggesterFactory {

	public function __construct(
		protected TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		protected ConfigurationLoader $configurationLoader,
		protected SearchStrategy $searchStrategy,
		protected NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		protected LinkBatchFactory $linkBatchFactory,
		protected StatusFormatter $statusFormatter
	) {
		$this->logger = new NullLogger();
	}

}
