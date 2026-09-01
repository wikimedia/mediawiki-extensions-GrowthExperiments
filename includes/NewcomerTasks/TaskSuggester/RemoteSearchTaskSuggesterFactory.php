<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * Factory for RemoteSearchTaskSuggester.
 */
class RemoteSearchTaskSuggesterFactory extends SearchTaskSuggesterFactory {

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param StatusFormatter $statusFormatter
	 * @param string $apiUrl Base URL of the remote API (ending with 'api.php').
	 * @param ITopicRegistry $topicRegistry
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		private readonly HttpRequestFactory $requestFactory,
		private readonly TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory,
		StatusFormatter $statusFormatter,
		private readonly string $apiUrl,
		private readonly ITopicRegistry $topicRegistry,
		LoggerInterface $logger
	) {
		parent::__construct(
			$taskTypeHandlerRegistry,
			$configurationLoader,
			$searchStrategy,
			$newcomerTasksUserOptionsLookup,
			$linkBatchFactory,
			$statusFormatter,
			$logger
		);
	}

	/** @inheritDoc */
	public function create(
		?ConfigurationLoader $customConfigurationLoader = null
	): RemoteSearchTaskSuggester|ErrorForwardingTaskSuggester {
		$configurationLoader = $customConfigurationLoader ?? $this->configurationLoader;
		$taskTypes = $configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return $this->createError( $taskTypes );
		}
		$topics = $this->topicRegistry->getTopics();
		$suggester = new RemoteSearchTaskSuggester(
			$this->taskTypeHandlerRegistry,
			$this->searchStrategy,
			$this->newcomerTasksUserOptionsLookup,
			$this->linkBatchFactory,
			$this->statusFormatter,
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
