<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use ISearchResultSet;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use MultipleIterator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use SearchResult;
use StatusValue;
use Wikimedia\Message\ListType;

/**
 * Shared functionality for local and remote search.
 */
abstract class SearchTaskSuggester implements TaskSuggester, LoggerAwareInterface {

	use LoggerAwareTrait;

	// Keep this in sync with GrowthTasksApi.js#fetchTasks
	public const DEFAULT_LIMIT = 15;

	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;

	/** @var SearchStrategy */
	protected $searchStrategy;

	/** @var NewcomerTasksUserOptionsLookup */
	private $newcomerTasksUserOptionsLookup;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var TaskType[] id => TaskType */
	protected $taskTypes = [];

	/** @var Topic[] id => Topic */
	protected $topics = [];

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LinkBatchFactory $linkBatchFactory,
		array $taskTypes,
		array $topics
	) {
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->searchStrategy = $searchStrategy;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->linkBatchFactory = $linkBatchFactory;
		foreach ( $taskTypes as $taskType ) {
			$this->taskTypes[$taskType->getId()] = $taskType;
		}
		foreach ( $topics as $topic ) {
			$this->topics[$topic->getId()] = $topic;
		}
		$this->logger = new NullLogger();
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	) {
		return $this->doSuggest( null, $user, $taskSetFilters, $limit, $offset,
			$options );
	}

	/** @inheritDoc */
	public function filter( UserIdentity $user, TaskSet $taskSet ) {
		$taskTypes = $taskSet->getFilters()->getTaskTypeFilters();

		$pageTitles = array_map( static function ( Task $task ) {
			return $task->getTitle();
		}, iterator_to_array( $taskSet ) );
		$linkBatch = $this->linkBatchFactory->newLinkBatch( $pageTitles );
		$pageIds = array_values( $linkBatch->execute() );

		// Topic filtering is slow and topic changes don't really invalidate tasks, so just copy
		// topic data from the old taskset instead.
		$taskSetFilters = new TaskSetFilters( $taskTypes, [] );
		$filteredTaskSet = $this->doSuggest( $pageIds, $user, $taskSetFilters, $taskSet->count() );
		if ( !$filteredTaskSet instanceof TaskSet ) {
			return $filteredTaskSet;
		}
		$filteredTasks = iterator_to_array( $filteredTaskSet );
		$this->mapTopicData( $taskSet, $filteredTasks );

		$subtracted = $taskSet->count() - $filteredTaskSet->count();
		$finalTaskSet = new TaskSet( $filteredTasks, $taskSet->getTotalCount() - $subtracted,
			$taskSet->getOffset(), $taskSet->getFilters(), $taskSet->getInvalidTasks() );
		$finalTaskSet->setDebugData( $taskSet->getDebugData() );
		return $finalTaskSet;
	}

	/**
	 * See suggest() for details. The only difference is that $pageIds can be used to restrict
	 * to a specific set of pages.
	 * @param array|null $pageIds List of page IDs to limit suggestions to.
	 * @param UserIdentity $user
	 * @param TaskSetFilters $taskSetFilters
	 * @param int|null $limit
	 * @param int|null $offset
	 * @param array $options Same as in suggest().
	 * @return TaskSet|StatusValue
	 */
	private function doSuggest(
		?array $pageIds,
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	) {
		$debug = $options['debug'] ?? false;

		// We generally don't try to handle task type filtering for the A/B test (T278123) here
		// as it is already handled in NewcomerTasksUserOptionsLookup, but we make an exception
		// for the case when $taskTypeFilter === [] which would be difficult to handle elsewhere.
		if ( !$taskSetFilters->getTaskTypeFilters() ) {
			$taskSetFilters->setTaskTypeFilters(
				$this->newcomerTasksUserOptionsLookup
					->filterTaskTypes( array_keys( $this->taskTypes ), $user )
			);
		}

		// FIXME these and task types should have similar validation rules
		$topics = array_values( array_intersect_key(
			$this->topics,
			array_flip( $taskSetFilters->getTopicFilters() )
		) );

		$limit ??= self::DEFAULT_LIMIT;
		// FIXME we are completely ignoring offset for now because 1) doing offsets when we are
		//   interleaving search results from multiple sources is hard, and 2) we are randomizing
		//   search results so offsets would not really be meaningful anyway.
		$offset = 0;
		$totalCount = 0;
		$matchIterator = new MultipleIterator( MultipleIterator::MIT_NEED_ANY |
			MultipleIterator::MIT_KEYS_ASSOC );

		$taskTypes = $invalidTaskTypes = [];
		$taskTypeFilter = $taskSetFilters->getTaskTypeFilters();
		foreach ( $taskTypeFilter as $taskTypeId ) {
			$taskType = $this->taskTypes[$taskTypeId] ?? null;
			if ( $taskType instanceof TaskType ) {
				$taskTypes[] = $taskType;
			} else {
				$invalidTaskTypes[] = $taskTypeId;
			}
		}

		if ( !$taskTypes ) {
			return StatusValue::newFatal(
				wfMessage( 'growthexperiments-newcomertasks-invalid-tasktype',
					Message::listParam( $invalidTaskTypes, ListType::COMMA )
				)
			);
		}

		$queries = $this->searchStrategy->getQueries(
			$taskTypes,
			$topics,
			$pageIds,
			$options['excludePageIds'] ?? null,
			$taskSetFilters->getTopicFiltersMode()
		);
		foreach ( $queries as $query ) {
			$matches = $this->search( $query, $limit, $offset, $debug );
			if ( $matches instanceof StatusValue ) {
				// Only log when there's a logger; Status::getWikiText would break unit tests.
				if ( !$this->logger instanceof NullLogger ) {
					$this->logger->warning( 'Search error: {message}', [
						'message' => Status::wrap( $matches )->getWikiText( false, false, 'en' ),
						'searchTerm' => $query->getQueryString(),
						'queryId' => $query->getId(),
						'limit' => $limit,
						'offset' => $offset,
					] );
				}
				return $matches;
			}
			$totalCount += $matches->getTotalHits();
			$matchIterator->attachIterator( Util::getIteratorFromTraversable( $matches ), $query->getId() );
		}

		$taskCount = 0;
		$suggestions = [];
		foreach ( $matchIterator as $matchSlice ) {
			/** @var SearchResult $match */
			foreach ( array_filter( $matchSlice ) as $queryId => $match ) {
				// TODO: Filter out pages that are protected.
				$query = $queries[$queryId];
				$taskType = $query->getTaskType();
				$suggestions[] = $this->taskTypeHandlerRegistry->getByTaskType( $taskType )
					->createTaskFromSearchResult( $query, $match );
				$taskCount++;
				if ( $taskCount >= $limit ) {
					break 2;
				}
			}
		}

		$suggestions = $this->deduplicateSuggestions( $suggestions );

		$taskSet = new TaskSet(
			$suggestions,
			$totalCount,
			$offset,
			$taskSetFilters
		);

		if ( $debug ) {
			$this->setDebugData( $taskSet, $queries );
		}
		return $taskSet;
	}

	/**
	 * @param SearchQuery $query
	 * @param int $limit
	 * @param int $offset
	 * @param bool $debug Store debug data so it can be set in setDebugData()
	 * @return ISearchResultSet|StatusValue Search results, or StatusValue on error.
	 */
	abstract protected function search(
		SearchQuery $query,
		int $limit,
		int $offset,
		bool $debug
	);

	/**
	 * Copy topic data from the tasks in $sourceTaskSet to the tasks in $targetTasks.
	 * @param TaskSet $sourceTaskSet
	 * @param Task[] $targetTasks
	 */
	private function mapTopicData( TaskSet $sourceTaskSet, array $targetTasks ) {
		$taskMap = [];
		foreach ( $sourceTaskSet as $task ) {
			$key = $task->getTitle()->getNamespace() . ':' . $task->getTitle()->getDBkey();
			$taskMap[$key] = $task;
		}
		foreach ( $targetTasks as $task ) {
			$key = $task->getTitle()->getNamespace() . ':' . $task->getTitle()->getDBkey();
			$sourceTask = $taskMap[$key] ?? null;
			if ( $sourceTask ) {
				$task->setTopics( $sourceTask->getTopics() );
			}
		}
	}

	/**
	 * Set extra debug data. Only called in debug mode.
	 * @param TaskSet $taskSet
	 * @param SearchQuery[] $queries
	 * @return void
	 */
	private function setDebugData( TaskSet $taskSet, array $queries ): void {
		$debugUrls = [];
		foreach ( $queries as $query ) {
			if ( $query->getDebugUrl() ) {
				$debugUrls[] = $query->getDebugUrl();
			}
		}
		$taskSet->setDebugData( [ 'searchDebugUrls' => $debugUrls ] );
	}

	/**
	 * Make sure there's only one task per article, even if an article is multiple task types / topics.
	 * @param Task[] $suggestions
	 * @return Task[]
	 */
	private function deduplicateSuggestions( array $suggestions ) {
		/** @var Task[] $deduped */
		$deduped = [];
		foreach ( $suggestions as $suggestion ) {
			$key = $suggestion->getTitle()->getNamespace() . ':' . $suggestion->getTitle()->getDBkey();
			if ( !isset( $deduped[$key] ) || $this->compareTasks( $suggestion, $deduped[$key] ) < 0 ) {
				$deduped[$key] = $suggestion;
			}
		}
		return array_values( $deduped );
	}

	/**
	 * Compare two tasks for sorting. Return an integer, like strcmp & co.
	 * Task types that come first in the configured task type list take precedence. Otherwise,
	 * it's topics that come first.
	 * @param Task $first
	 * @param Task $second
	 * @return int
	 */
	private function compareTasks( Task $first, Task $second ): int {
		$taskTypePosFirst = array_search( $first->getTaskType()->getId(),
			array_keys( $this->taskTypes ), true );
		$taskTypePosSecond = array_search( $second->getTaskType()->getId(),
			array_keys( $this->taskTypes ), true );
		// There should be at most one topic (otherwise we won't need the compare logic).
		// No topic precedes any topic (although that comparison should never happen).
		$topicPosFirst = $first->getTopics() ? array_search( $first->getTopics()[0]->getId(),
			array_keys( $this->topics ), true ) : -9999;
		$topicPosSecond = $second->getTopics() ? array_search( $second->getTopics()[0]->getId(),
			array_keys( $this->topics ), true ) : -9999;
		return ( $taskTypePosFirst - $taskTypePosSecond ) ?: ( $topicPosFirst - $topicPosSecond );
	}

}
