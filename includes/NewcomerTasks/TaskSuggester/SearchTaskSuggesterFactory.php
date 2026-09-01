<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleParser;
use Psr\Log\LoggerInterface;

abstract class SearchTaskSuggesterFactory extends ErrorCapableTaskSuggesterFactory {

	public function __construct(
		protected TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		protected ConfigurationLoader $configurationLoader,
		protected SearchStrategy $searchStrategy,
		protected NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		protected LinkBatchFactory $linkBatchFactory,
		StatusFormatter $statusFormatter,
		protected TitleParser $titleParser,
		LoggerInterface $logger
	) {
		parent::__construct( $statusFormatter, $logger );
	}

}
