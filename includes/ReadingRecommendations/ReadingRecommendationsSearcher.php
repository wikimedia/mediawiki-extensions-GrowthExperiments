<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use GrowthExperiments\NewcomerTasks\TaskType\Util;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Search\ISearchResultSet;
use MediaWiki\Search\SearchEngineFactory;
use Psr\Log\LoggerInterface;
use StatusValue;
use Throwable;
use Wikimedia\Stats\StatsFactory;

/**
 * Finds candidate articles for reading recommendations via full-text search.
 *
 * This is the only reading recommendations class that talks to SearchEngine.
 * Every failure mode (search error or exception) yields an error result with
 * no titles, so a search outage degrades the module instead of breaking
 * Special:Homepage. A missing search engine is a successful empty result.
 */
class ReadingRecommendationsSearcher {

	private const TIMING_METRIC = 'reading_recommendations_search_seconds';

	private StatsFactory $statsFactory;

	/**
	 * @param SearchEngineFactory|null $searchEngineFactory Null when CirrusSearch is
	 *   not the configured search engine; every search then returns an empty list.
	 * @param StatsFactory $statsFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly ?SearchEngineFactory $searchEngineFactory,
		StatsFactory $statsFactory,
		private readonly LoggerInterface $logger
	) {
		$this->statsFactory = $statsFactory->withComponent( 'GrowthExperiments' );
	}

	/**
	 * Random articles from a category, stable for a given seed.
	 *
	 * @param LinkTarget $category
	 * @param int $limit
	 * @param int $seed
	 * @return ReadingRecommendationsSearchResult
	 */
	public function findFeatured( LinkTarget $category, int $limit, int $seed ): ReadingRecommendationsSearchResult {
		return $this->search(
			'incategory:' . Util::escapeSearchTitleList( [ $category ] ),
			$limit,
			'featured',
			'random',
			$seed
		);
	}

	/**
	 * @param string $query
	 * @param int $limit
	 * @param string $source Metric label for the kind of search
	 * @param string|null $sort
	 * @param int|null $seed
	 * @return ReadingRecommendationsSearchResult
	 */
	private function search(
		string $query,
		int $limit,
		string $source,
		?string $sort,
		?int $seed
	): ReadingRecommendationsSearchResult {
		if ( !$this->searchEngineFactory ) {
			return ReadingRecommendationsSearchResult::newSuccess( [] );
		}

		$start = microtime( true );
		$searchResult = ReadingRecommendationsSearchResult::newError();
		$result = 'error';
		try {
			$searchEngine = $this->searchEngineFactory->create();
			$searchEngine->setLimitOffset( $limit, 0 );
			$searchEngine->setNamespaces( [ NS_MAIN ] );
			$searchEngine->setShowSuggestion( false );
			if ( $sort !== null && in_array( $sort, $searchEngine->getValidSorts(), true ) ) {
				$searchEngine->setSort( $sort );
				if ( $seed !== null ) {
					$searchEngine->setFeatureData( 'random_seed', $seed );
				}
			}

			$matches = $searchEngine->searchText( $query );
			if ( $matches instanceof StatusValue && $matches->isGood() ) {
				$matches = $matches->getValue();
			}
			if ( $matches instanceof ISearchResultSet ) {
				$titles = $matches->extractTitles();
				$searchResult = ReadingRecommendationsSearchResult::newSuccess( $titles );
				$result = $titles ? 'ok' : 'empty';
			} else {
				$this->logger->warning( 'ReadingRecommendationsSearcher: search failed', [
					'source' => $source,
					'status' => $matches instanceof StatusValue ? (string)$matches : 'null',
				] );
			}
		} catch ( Throwable $e ) {
			$this->logger->warning( 'ReadingRecommendationsSearcher: search threw', [
				'source' => $source,
				'exceptionClass' => get_class( $e ),
			] );
		}

		$this->statsFactory->getTiming( self::TIMING_METRIC )
			->setLabel( 'source', $source )
			->setLabel( 'result', $result )
			->observeSeconds( microtime( true ) - $start );

		return $searchResult;
	}
}
