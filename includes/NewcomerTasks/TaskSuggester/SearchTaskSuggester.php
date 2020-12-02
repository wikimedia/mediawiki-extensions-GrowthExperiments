<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use CirrusSearch\Search\CirrusSearchResult;
use GrowthExperiments\NewcomerTasks\FauxSearchResultWithScore;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use ISearchResultSet;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use MultipleIterator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use SearchResult;
use Status;
use StatusValue;

/**
 * Shared functionality for local and remote search.
 */
abstract class SearchTaskSuggester implements TaskSuggester, LoggerAwareInterface {

	use LoggerAwareTrait;

	// Keep this in sync with GrowthTasksApi.js#fetchTasks
	// FIXME: Export this constant to client-side.
	public const DEFAULT_LIMIT = 250;

	/** @var TaskType[] id => TaskType */
	protected $taskTypes = [];

	/** @var LinkTarget[] List of templates which disqualify a page from being recommendable. */
	protected $templateBlacklist;

	/** @var Topic[] id => Topic */
	protected $topics = [];

	/** @var SearchStrategy */
	protected $searchStrategy;

	/**
	 * @param SearchStrategy $searchStrategy
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 * @param LinkTarget[] $templateBlacklist
	 */
	public function __construct(
		SearchStrategy $searchStrategy,
		array $taskTypes,
		array $topics,
		array $templateBlacklist
	) {
		$this->searchStrategy = $searchStrategy;
		foreach ( $taskTypes as $taskType ) {
			$this->taskTypes[$taskType->getId()] = $taskType;
		}
		foreach ( $topics as $topic ) {
			$this->topics[$topic->getId()] = $topic;
		}
		$this->templateBlacklist = $templateBlacklist;
		$this->logger = new NullLogger();
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = [],
		array $topicFilter = [],
		$limit = null,
		$offset = null,
		$debug = false,
		$useCache = true
	) {
		$taskTypeFilter = $taskTypeFilter ?: array_keys( $this->taskTypes );

		// FIXME these and task types should have similar validation rules
		$topics = array_values( array_intersect_key( $this->topics, array_flip( $topicFilter ) ) );

		$limit = $limit ?? self::DEFAULT_LIMIT;
		// FIXME we are completely ignoring offset for now because 1) doing offsets when we are
		//   interleaving search results from multiple sources is hard, and 2) we are randomizing
		//   search results so offsets would not really be meaningful anyway.
		$offset = 0;
		$totalCount = 0;
		$matchIterator = new MultipleIterator( MultipleIterator::MIT_NEED_ANY |
			MultipleIterator::MIT_KEYS_ASSOC );

		$taskTypes = [];
		foreach ( $taskTypeFilter as $taskTypeId ) {
			$taskType = $this->taskTypes[$taskTypeId] ?? null;
			if ( !$taskType ) {
				return StatusValue::newFatal( wfMessage( 'growthexperiments-newcomertasks-invalid-tasktype',
					$taskTypeId ) );
			} elseif ( !( $taskType instanceof TemplateBasedTaskType ) ) {
				$this->logger->notice( 'Invalid task type: {taskType}', [
					'taskType' => get_class( $taskType ),
				] );
				continue;
			}
			$taskTypes[] = $taskType;
		}

		$queries = $this->searchStrategy->getQueries( $taskTypes, $topics, $this->templateBlacklist );
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
			foreach ( array_filter( $matchSlice ) as $queryId => $match ) {
				// TODO: Filter out pages that are protected.
				/** @var $match SearchResult */
				$taskType = $queries[$queryId]->getTaskType();
				$topic = $queries[$queryId]->getTopic();
				$task = new Task( $taskType, $match->getTitle() );
				if ( $topic ) {
					$score = 0;
					// CirrusSearch is an optional dependency, prevent phan from complaining
					// @phan-suppress-next-line PhanUndeclaredClassInstanceof
					if ( $match instanceof CirrusSearchResult || $match instanceof FauxSearchResultWithScore ) {
						// @phan-suppress-next-line PhanUndeclaredClassMethod
						$score = $match->getScore();
					}
					$task->setTopics( [ $topic ], [ $topic->getId() => $score ] );
				}
				$suggestions[] = $task;
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
			new TaskSetFilters( $taskTypeFilter, $topicFilter )
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
	 * Set extra debug data. Only called in debug mode.
	 * @param TaskSet $taskSet
	 * @param SearchQuery[] $queries
	 * @return void
	 */
	private function setDebugData( TaskSet $taskSet, array $queries ) : void {
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
	private function compareTasks( Task $first, Task $second ) : int {
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
