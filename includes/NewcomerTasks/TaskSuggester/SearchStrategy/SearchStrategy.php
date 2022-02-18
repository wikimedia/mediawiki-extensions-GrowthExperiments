<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy;

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
	 * @return SearchQuery[] Array of queries, indexed by query ID.
	 */
	public function getQueries(
		array $taskTypes, array $topics, array $pageIds = null, array $excludePageIds = null
	) {
		$this->validateParams( $taskTypes, $topics );
		$queries = [];
		// FIXME Ideally we should do a single search for all topics, but currently this
		//   runs into query length limits (T242560)
		// Empty topic array means doing a single search with no topic filter
		$topics = $topics ?: [ null ];
		foreach ( $taskTypes as $taskType ) {
			foreach ( $topics as $topic ) {
				$typeTerm = $this->taskTypeHandlerRegistry->getByTaskType( $taskType )
					->getSearchTerm( $taskType );
				$topicTerm = $this->getTopicTerm( $topic );
				$pageIdTerm = $pageIds ? $this->getPageIdTerm( $pageIds ) : null;
				$excludedPageIdTerm = $excludePageIds ? $this->getExcludedPageIdTerm( $excludePageIds ) : null;
				$queryString = implode( ' ', array_filter( [ $typeTerm, $topicTerm,
					$pageIdTerm, $excludedPageIdTerm ] ) );

				$queryId = $taskType->getId() . ':' . ( $topic ? $topic->getId() : '-' );
				$query = new SearchQuery( $queryId, $queryString, $taskType, $topic );
				// don't randomize if we use topic matching with the morelike backend, which itself
				// is a kind of sorting. Topic matching with the ORES backend already uses
				// thresholds per topic so applying a random sort should be safe.
				if ( !$topic || $topic instanceof OresBasedTopic ) {
					$query->setSort( 'random' );
				}
				$queries[$queryId] = $query;
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
		Assert::parameterElementType( [ OresBasedTopic::class,  MorelikeBasedTopic::class,
			CampaignTopic::class ], $topics, '$topics' );
	}

	/**
	 * @param LinkTarget[] $templates
	 * @return string|null
	 */
	protected function getTemplateTerm( array $templates ) {
		return 'hastemplate:' . $this->escapeSearchTitleList( $templates );
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

}
