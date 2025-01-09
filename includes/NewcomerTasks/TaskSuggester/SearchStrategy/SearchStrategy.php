<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
use Wikimedia\Assert\Assert;

/**
 * SearchStrategy turns requirements from the user (such as task types and topics)
 * into a series of search query strings.
 */
class SearchStrategy {

	public const TOPIC_MATCH_MODE_OR = 'OR';
	public const TOPIC_MATCH_MODE_AND = 'AND';
	public const TOPIC_MATCH_MODES = [
		self::TOPIC_MATCH_MODE_OR,
		self::TOPIC_MATCH_MODE_AND
	];

	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	) {
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
	}

	/**
	 * Get the search queries for searching for a given user requirement
	 * (set of task types and topics).
	 * @param TaskType[] $taskTypes Task types to limit search results to
	 * @param Topic[] $topics Topics to limit search results to
	 * @param array|null $pageIds List of PageIds search results should be restricted to.
	 * @param array|null $excludePageIds List of PageIds to exclude from search.
	 * @param string|null $topicsFilterMode Join mode for the topics search. One of ('AND', 'OR').
	 * @return SearchQuery[] Array of queries, indexed by query ID.
	 */
	public function getQueries(
		array $taskTypes,
		array $topics,
		?array $pageIds = null,
		?array $excludePageIds = null,
		?string $topicsFilterMode = null
	) {
		$this->validateParams( $taskTypes, $topics );
		$queries = [];
		// FIXME Ideally we should do a single search for all topics, but currently this
		//   runs into query length limits (T242560)
		// Empty topic array means doing a single search with no topic filter
		$topics = $topics ?: [ null ];
		$allTopicsAreOres = $this->isOresTopicSet( $topics );
		foreach ( $taskTypes as $taskType ) {
			$typeTerm = $this->taskTypeHandlerRegistry->getByTaskType( $taskType )
				->getSearchTerm( $taskType );
			$pageIdTerm = $pageIds ? $this->getPageIdTerm( $pageIds ) : null;
			$excludedPageIdTerm = $excludePageIds ? $this->getExcludedPageIdTerm( $excludePageIds ) : null;
			if ( $topicsFilterMode === self::TOPIC_MATCH_MODE_AND ) {
				$query = $this->getMultiTopicIntersectionQuery(
					$topics, $typeTerm, $pageIdTerm, $excludedPageIdTerm, $taskType
				);
				// don't randomize if we use topic matching with the morelike backend, which itself
				// is a kind of sorting. Topic matching with the ORES backend already uses
				// thresholds per topic so applying a random sort should be safe.
				if ( $allTopicsAreOres ) {
					$query->setSort( 'random' );
				}
				$queries[$query->getId()] = $query;
			} else {
				if ( $allTopicsAreOres ) {
					$query = $this->getMultiTopicUnionQuery(
						$topics, $typeTerm, $pageIdTerm, $excludedPageIdTerm, $taskType
					);
					// don't randomize if we use topic matching with the morelike backend, which itself
					// is a kind of sorting. Topic matching with the ORES backend already uses
					// thresholds per topic so applying a random sort should be safe.
					$query->setSort( 'random' );
					$queries[$query->getId()] = $query;
				} else {
					foreach ( $topics as $topic ) {
						$query = $this->getPerTopicQuery(
							$topic, $typeTerm, $pageIdTerm, $excludedPageIdTerm, $taskType
						);
						if ( !$topic || $topic instanceof OresBasedTopic ) {
							$query->setSort( 'random' );
						}
						$queries[$query->getId()] = $query;
					}
				}
			}
			if (
				$taskType instanceof LinkRecommendationTaskType
				&& $taskType->getUnderlinkedWeight() > 0
				&& !$pageIdTerm
			) {
				// Sort link recommendation tasks by underlinkedness.
				// Cirrus will only rescore when the sort mode is 'relevance' so we can't use
				// random sorting. It probably doesn't matter much: we are typically aiming for
				// 32K tasks per wiki, and the top <rescore window size> * <shard count> results
				// will be rescored; in practice, that's $wmgCirrusSearchShardCount * 8K results,
				// so a fairly large part of the total result set will be included anyway.
				$query->setSort( 'relevance' );
				$query->setRescoreProfile( SearchQuery::RESCORE_UNDERLINKED );
			}
		}
		return $this->shuffleQueryOrder( $queries );
	}

	/**
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 */
	protected function validateParams( array $taskTypes, array $topics ) {
		Assert::parameterElementType( TaskType::class, $taskTypes, '$taskTypes' );
		Assert::parameterElementType( [ OresBasedTopic::class, MorelikeBasedTopic::class,
			CampaignTopic::class ], $topics, '$topics' );
	}

	/**
	 * @param Topic|null $topic
	 * @return string|null
	 */
	protected function getTopicTerm( ?Topic $topic ): ?string {
		$topicTerm = null;
		if ( $topic instanceof OresBasedTopic ) {
			$topicTerm = $this->getOresBasedTopicTerm( [ $topic ] );
		} elseif ( $topic instanceof MorelikeBasedTopic ) {
			$topicTerm = $this->getMorelikeBasedTopicTerm( [ $topic ] );
		} elseif ( $topic instanceof CampaignTopic ) {
			$topicTerm = $topic->getSearchExpression();
		}
		return $topicTerm;
	}

	private function isOresTopicSet( array $topics ): bool {
		return array_reduce( $topics, static function ( $allAreOres, $topic ) {
			return $allAreOres && $topic instanceof OresBasedTopic;
		}, true );
	}

	/**
	 * @param Topic[] $topics
	 * @return string|null
	 */
	protected function getTopicsTerms( array $topics ): ?string {
		$topicsByType = [ [], [], [] ];
		foreach ( $topics as $topic ) {
			if ( $topic instanceof OresBasedTopic ) {
				array_push( $topicsByType[0], $topic );
			} elseif ( $topic instanceof MorelikeBasedTopic ) {
				array_push( $topicsByType[1], $topic );
			} elseif ( $topic instanceof CampaignTopic ) {
				array_push( $topicsByType[2], $topic->getSearchExpression() );
			}
		}
		$terms = [];
		if ( count( $topicsByType[0] ) > 0 ) {
			array_push( $terms, $this->getOresBasedTopicTerm( $topicsByType[0] ) );
		}
		if ( count( $topicsByType[1] ) > 0 ) {
			array_push( $terms, $this->getMorelikeBasedTopicTerm( $topicsByType[1] ) );
		}
		if ( count( $topicsByType[2] ) > 0 ) {
			array_push( $terms, implode( '', $topicsByType[2] ) );
		}
		return implode( ' ', $terms );
	}

	/**
	 * @param array $pageIds
	 * @return string
	 */
	private function getPageIdTerm( array $pageIds ) {
		return 'pageid:' . implode( '|', $pageIds );
	}

	/**
	 * @param array $pageIds
	 * @return string
	 */
	private function getExcludedPageIdTerm( array $pageIds ): string {
		return '-pageid:' . implode( '|', $pageIds );
	}

	/**
	 * @param OresBasedTopic[] $topics
	 * @return string
	 */
	protected function getOresBasedTopicTerm( array $topics ) {
		return 'articletopic:' . implode( '|', array_reduce( $topics,
			static function ( array $carry, OresBasedTopic $topic ) {
				return array_merge( $carry, $topic->getOresTopics() );
			}, [] ) );
	}

	/**
	 * @param MorelikeBasedTopic[] $topics
	 * @return string
	 * @see https://www.mediawiki.org/wiki/Help:CirrusSearch#Morelike
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-mlt-query.html
	 */
	protected function getMorelikeBasedTopicTerm( array $topics ) {
		return 'morelikethis:' . $this->escapeSearchTitleList(
			array_reduce( $topics, static function ( array $carry, MorelikeBasedTopic $topic ) {
				return array_merge( $carry, $topic->getReferencePages() );
			}, [] ) );
	}

	/**
	 * Turns an array of pages into a CirrusSearch keyword value (pipe-separated, escaped).
	 * Namespaces are omitted entirely.
	 * @param LinkTarget[] $titles
	 * @return string
	 */
	protected function escapeSearchTitleList( array $titles ) {
		return '"' . implode( '|', array_map( static function ( LinkTarget $title ) {
			return str_replace( [ '"', '?' ], [ '\"', '\?' ], $title->getDBkey() );
		}, $titles ) ) . '"';
	}

	/**
	 * Shuffle the list of queries, preserving keys (T248106)
	 *
	 * PHP's shuffle() is insufficient as we need to preserve the keys.
	 *
	 * @param array $queries
	 * @return array
	 */
	protected function shuffleQueryOrder( array $queries ): array {
		$keys = array_keys( $queries );
		shuffle( $keys );
		$shuffled = [];
		foreach ( $keys as $key ) {
			$shuffled[$key] = $queries[$key];
		}
		return $shuffled;
	}

	/**
	 * Get a query for multiple topics intersection
	 */
	private function getMultiTopicIntersectionQuery(
		array $topics,
		string $typeTerm,
		?string $pageIdTerm,
		?string $excludedPageIdTerm,
		TaskType $taskType
	): SearchQuery {
		$topicTerms = [];
		foreach ( $topics as $topic ) {
			$topicTerms[] = $this->getTopicTerm( $topic );
		}
		$topicTerm = implode( ' ', array_filter( $topicTerms ) );
		$queryString = implode( ' ', array_filter( [ $typeTerm, $topicTerm,
			$pageIdTerm, $excludedPageIdTerm ] ) );

		$queryId = $taskType->getId() . ':multiple-topics-intersection';
		return new SearchQuery( $queryId, $queryString, $taskType, $topics );
	}

	/**
	 * Get a query for a single topic
	 */
	private function getPerTopicQuery(
		?Topic $topic, string $typeTerm, ?string $pageIdTerm, ?string $excludedPageIdTerm, TaskType $taskType
	): SearchQuery {
		$topicTerm = $this->getTopicTerm( $topic );
		$queryString = implode( ' ', array_filter( [ $typeTerm, $topicTerm,
			$pageIdTerm, $excludedPageIdTerm ] ) );

		$queryId = $taskType->getId() . ':' . ( $topic ? $topic->getId() : '-' );
		return new SearchQuery( $queryId, $queryString, $taskType, [ $topic ] );
	}

	/**
	 * Get a query for multiple topics union
	 */
	private function getMultiTopicUnionQuery(
		array $topics, string $typeTerm, ?string $pageIdTerm, ?string $excludedPageIdTerm, TaskType $taskType
	): SearchQuery {
		$topicTerm = '';
		if ( $topics[0] !== null ) {
			$topicTerm = $this->getTopicsTerms( $topics );
		}
		$queryString = implode( ' ', array_filter( [ $typeTerm, $topicTerm,
			$pageIdTerm, $excludedPageIdTerm ] ) );

		$queryId = $taskType->getId() . ':multiple-topics-union';
		return new SearchQuery( $queryId, $queryString, $taskType, $topics );
	}

}
