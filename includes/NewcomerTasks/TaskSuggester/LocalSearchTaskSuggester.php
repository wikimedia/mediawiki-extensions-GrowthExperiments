<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Search\ISearchResultSet;
use MediaWiki\Search\SearchEngine;
use MediaWiki\Search\SearchEngineFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\Stats\StatsFactory;

/**
 * Suggest edits based on searching the wiki via SearchEngine.
 */
class LocalSearchTaskSuggester extends SearchTaskSuggester {

	private StatsFactory $statsFactory;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param SearchEngineFactory $searchEngineFactory
	 * @param SearchStrategy $searchStrategy
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param StatusFormatter $statusFormatter
	 * @param TitleParser $titleParser
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 * @param StatsFactory $statsFactory
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		private readonly SearchEngineFactory $searchEngineFactory,
		SearchStrategy $searchStrategy,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LinkBatchFactory $linkBatchFactory,
		StatusFormatter $statusFormatter,
		TitleParser $titleParser,
		array $taskTypes,
		array $topics,
		StatsFactory $statsFactory,
		private readonly string $wikiId,
	) {
		parent::__construct( $taskTypeHandlerRegistry, $searchStrategy, $newcomerTasksUserOptionsLookup,
			$linkBatchFactory, $statusFormatter, $titleParser, $taskTypes, $topics );
		$this->statsFactory = $statsFactory->withComponent( 'GrowthExperiments' );
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	): TaskSet|StatusValue {
		$queryType = $taskSetFilters->getInterestFilters() ? 'interests' : 'topics';
		$timer = $this->statsFactory->getTiming( 'local_search_task_suggester_suggest_seconds' )
			->setLabel( 'wiki', $this->wikiId )
			->setLabel( 'query_type', $queryType )
			->start();
		$suggest = parent::suggest( $user, $taskSetFilters, $limit, $offset, $options );
		$timer->stop();

		return $suggest;
	}

	/** @inheritDoc */
	public function filter( UserIdentity $user, TaskSet $taskSet ): TaskSet|StatusValue {
		$timer = $this->statsFactory->getTiming( 'local_search_task_suggester_filter_seconds' )
			->setLabel( 'wiki', $this->wikiId )
			->setLabel( 'query_type', $taskSet->getFilters()->getInterestFilters() ? 'interests' : 'topics' )
			->start();
		$filter = parent::filter( $user, $taskSet );
		$timer->stop();

		return $filter;
	}

	/** @inheritDoc */
	protected function search(
		SearchQuery $query,
		int $limit,
		int $offset,
		bool $debug,
	): ISearchResultSet|StatusValue {
		$isInterestsQuery = $query->getTopics() && $query->getTopics()[0] instanceof InterestBasedTopic;
		$timer = $this->statsFactory->getTiming( 'local_search_task_suggester_search_seconds' )
			->setLabel( 'wiki', $this->wikiId )
			->setLabel( 'query_type', $isInterestsQuery ? 'interests' : 'topics' )
			->start();
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
		} elseif ( $matches instanceof StatusValue && $matches->isGood() ) {
			$matches = $matches->getValue();
			/** @var ISearchResultSet $matches */
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
		$timer->setLabel( 'status', $matches instanceof ISearchResultSet ? 'success' : 'error' );
		$timer->stop();
		$this->logger->debug( 'LocalSearchTaskSuggester query', [
			'query' => $query->getQueryString(),
			'sort' => $query->getSort(),
			'limit' => $limit,
			'success' => $matches instanceof ISearchResultSet,
		] );

		return $matches;
	}

}
