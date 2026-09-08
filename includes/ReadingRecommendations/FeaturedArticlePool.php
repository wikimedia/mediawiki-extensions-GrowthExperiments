<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * A per-wiki, per-day pool of articles from the configured Featured category.
 *
 * The pool is the source of the general recommendations. One seeded random
 * search per wiki per day; all users share the pool through the cache. An
 * empty or unparseable category configuration means an empty pool.
 */
class FeaturedArticlePool {

	public const POOL_SIZE = 50;
	private const CACHE_VERSION = 1;

	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageReadingRecommendationsFeaturedCategory',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly WANObjectCache $wanCache,
		private readonly TitleParser $titleParser,
		private readonly ReadingRecommendationsSearcher $searcher,
		private readonly ReadingRecommendationsCachePolicy $cachePolicy,
		private readonly LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * @param WikiDay $day
	 * @return ReadingRecommendationsSearchResult
	 */
	public function getPool( WikiDay $day ): ReadingRecommendationsSearchResult {
		$categoryConfig = $this->options->get( 'GEHomepageReadingRecommendationsFeaturedCategory' );
		if ( $categoryConfig === '' ) {
			return ReadingRecommendationsSearchResult::newSuccess( [] );
		}
		try {
			$category = $this->titleParser->parseTitle( $categoryConfig, NS_CATEGORY );
		} catch ( MalformedTitleException ) {
			$this->logger->warning( 'FeaturedArticlePool: invalid featured category configuration', [
				'category' => $categoryConfig,
			] );
			return ReadingRecommendationsSearchResult::newSuccess( [] );
		}

		/** @var array{titles: array<int, array{ns: int, dbkey: string}>, error: bool} $result */
		$result = $this->wanCache->getWithSetCallback(
			// The category and the pool size are part of the key so that reconfiguring
			// either takes effect on the next request instead of at the next local
			// midnight.
			$this->wanCache->makeKey(
				'growthexperiments-reading-recommendations-featured',
				$day->getDate(),
				$category->getNamespace(),
				$category->getDBkey(),
				self::POOL_SIZE
			),
			WANObjectCache::TTL_DAY,
			function ( $oldValue, &$ttl ) use ( $category, $day ) {
				$searchResult = $this->searcher->findFeatured(
					$category,
					self::POOL_SIZE,
					crc32( $day->getDate() )
				);
				if ( $searchResult->isError() ) {
					$ttl = 5 * WANObjectCache::TTL_MINUTE;
				}
				return [
					'titles' => array_map( static fn ( LinkTarget $title ) => [
						'ns' => $title->getNamespace(),
						'dbkey' => $title->getDBkey(),
					], $searchResult->getTitles() ),
					'error' => $searchResult->isError(),
				];
			},
			$this->cachePolicy->getCacheOptions( self::CACHE_VERSION )
		);
		if ( $result['error'] ) {
			return ReadingRecommendationsSearchResult::newError();
		}
		$titles = array_map(
			static fn ( array $row ) => new TitleValue( $row['ns'], $row['dbkey'] ),
			$result['titles']
		);
		return ReadingRecommendationsSearchResult::newSuccess( $titles );
	}
}
