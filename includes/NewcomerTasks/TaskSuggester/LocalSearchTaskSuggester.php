<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use ApiRawMessage;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use IBufferingStatsdDataFactory;
use ISearchResultSet;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use SearchEngine;
use SearchEngineFactory;
use StatusValue;

/**
 * Suggest edits based on searching the wiki via SearchEngine.
 */
class LocalSearchTaskSuggester extends SearchTaskSuggester {

	/** @var SearchEngineFactory */
	private $searchEngineFactory;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param SearchEngineFactory $searchEngineFactory
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		SearchEngineFactory $searchEngineFactory,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LinkBatchFactory $linkBatchFactory,
		array $taskTypes,
		array $topics,
		IBufferingStatsdDataFactory $statsdDataFactory
	) {
		parent::__construct( $taskTypeHandlerRegistry, $searchStrategy, $newcomerTasksUserOptionsLookup,
			$linkBatchFactory, $taskTypes, $topics );
		$this->searchEngineFactory = $searchEngineFactory;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	) {
		$start = microtime( true );
		$suggest = parent::suggest( $user, $taskSetFilters, $limit, $offset, $options );
		$this->statsdDataFactory->timing(
			'timing.growthExperiments.SearchTaskSuggester.suggest', microtime( true ) - $start
		);
		return $suggest;
	}

	/** @inheritDoc */
	public function filter( UserIdentity $user, TaskSet $taskSet ) {
		$start = microtime( true );
		$filter = parent::filter( $user, $taskSet );
		$this->statsdDataFactory->timing(
			'timing.growthExperiments.SearchTaskSuggester.filter', microtime( true ) - $start
		);
		return $filter;
	}

	/**
	 * @inheritDoc
	 */
	protected function search(
		SearchQuery $query,
		int $limit,
		int $offset,
		bool $debug
	) {
		$start = microtime( true );
		$searchEngine = $this->searchEngineFactory->create();
		$searchEngine->setLimitOffset( $limit, $offset );
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setShowSuggestion( false );
		$searchEngine->setFeatureData(
			SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE,
			$query->getRescoreProfile() ?? 'classic_noboostlinks'
		);
		$sort = $query->getSort();
		if ( $sort ) {
			$searchEngine->setSort( $sort );
		}
		$matches = $searchEngine->searchText( $query->getQueryString() );
		if ( !$matches ) {
			$matches = StatusValue::newFatal( new ApiRawMessage(
				'Full text searches are unsupported or disabled',
				'grothexperiments-no-fulltext-search'
			) );
		} elseif ( $matches instanceof StatusValue ) {
			if ( $matches->isGood() ) {
				$matches = $matches->getValue();
				/** @var ISearchResultSet $matches */
			} else {
				// T302473 make sure the result is serializable
				$matches->setResult( false, null );
			}
		}

		if ( $debug ) {
			$params = [
				'search' => $query->getQueryString(),
				'fulltext' => 1,
				'ns0' => 1,
				'limit' => $limit,
				'offset' => $offset,
				'cirrusRescoreProfile' => $query->getRescoreProfile() ?? 'classic_noboostlinks',
				'cirrusDumpResult' => 1,
				'cirrusExplain' => 'pretty',
			];
			if ( $query->getSort() ) {
				$params['sort'] = $query->getSort();
			}
			$query->setDebugUrl( SpecialPage::getTitleFor( 'Search' )
				->getFullURL( $params, false, PROTO_CANONICAL ) );
		}
		$elapsed = microtime( true ) - $start;
		$this->logger->debug( 'LocalSearchTaskSuggester query', [
			'query' => $query->getQueryString(),
			'sort' => $query->getSort(),
			'limit' => $limit,
			'success' => $matches instanceof ISearchResultSet,
			'elapsedTime' => $elapsed
		] );
		$this->statsdDataFactory->timing( 'timing.growthExperiments.LocalSearchTaskSuggester.search', $elapsed );

		return $matches;
	}

}
