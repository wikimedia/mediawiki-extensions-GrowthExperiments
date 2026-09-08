<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Computes a user's daily reading recommendations.
 *
 * Selection rules, deterministic from the interest list and the day, so two users with the same
 * interests in the same order see the same recommendations and a cache miss regenerates the same
 * list from the same candidate lists.
 * Nothing else about the user enters the selection.
 *  1. Take up to SLOTS consecutive interests from the user's list, starting at the day number
 *     modulo the list length. With more than SLOTS interests this rotates through them on
 *     consecutive days.
 *  2. For each chosen interest, fetch CANDIDATES_PER_INTEREST related articles and pick the one at
 *     the day number modulo the candidate count, walking forward past any that is an interest
 *     article or already selected. The candidate list is cached per interest, without the day, and
 *     shared across users, so users sharing an interest pick from the identical list. The pick
 *     itself can still differ between them, because the walk skips their other interests and the
 *     articles already in their earlier slots.
 *  3. Fill remaining slots from the day's Featured pool, which is shared by everyone on the wiki,
 *     applying the same exclusions. So which pool articles a user ends up with does depend on their
 *     interests, even though the pool itself does not.
 */
class ReadingRecommendationsService {

	public const SLOTS = 4;
	public const CANDIDATES_PER_INTEREST = 50;
	public const CACHE_VERSION = 1;

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::Localtimezone,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly WANObjectCache $wanCache,
		private readonly InterestArticlesLookup $interestArticlesLookup,
		private readonly FeaturedArticlePool $featuredArticlePool,
		private readonly ReadingRecommendationsSearcher $searcher,
		private readonly ReadingRecommendationsCachePolicy $cachePolicy
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * The user's recommendations for today: up to SLOTS items.
	 *
	 * A user with no interests gets the shared general list from the pool, which has its own
	 * per-day cache.
	 *
	 * Lists for users with interests are cached by local date and a hash of the interest list in
	 * its stored order, so old days age out and a change of interests refreshes the list at once.
	 * Two users share an entry only when their interest lists are identical and in the same order,
	 * so in practice this is close to one entry per user per day; it saves the per-interest and
	 * pool cache reads on a hit, not the searches, which are already shared.
	 *
	 * @param UserIdentity $user
	 * @return ReadingRecommendation[]
	 */
	public function getRecommendations( UserIdentity $user ): array {
		$day = WikiDay::today( $this->options->get( MainConfigNames::Localtimezone ) );
		$interests = $this->interestArticlesLookup->getInterests( $user );
		if ( !$interests ) {
			return array_map(
				static fn ( LinkTarget $title ) => new ReadingRecommendation( $title ),
				array_slice( $this->featuredArticlePool->getPool( $day )->getTitles(), 0, self::SLOTS )
			);
		}
		$interestsHash = sha1( implode( '|', array_map(
			static fn ( LinkTarget $interest ) => $interest->getDBkey(),
			$interests
		) ) );

		$rows = $this->wanCache->getWithSetCallback(
			$this->wanCache->makeKey(
				'growthexperiments-reading-recommendations',
				$day->getDate(),
				$interestsHash
			),
			WANObjectCache::TTL_DAY,
			function ( $oldValue, &$ttl ) use ( $day, $interests ) {
				$selection = $this->selectRecommendations( $day, $interests );
				if ( $selection['hadSearchError'] ) {
					$ttl = 5 * WANObjectCache::TTL_MINUTE;
				}
				return array_map(
					static fn ( ReadingRecommendation $recommendation ) => $recommendation->toArray(),
					$selection['recommendations']
				);
			},
			$this->cachePolicy->getCacheOptions( self::CACHE_VERSION )
		);
		return array_map( [ ReadingRecommendation::class, 'fromArray' ], $rows );
	}

	/**
	 * Whether the user has at least one valid interest article configured.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function hasInterests( UserIdentity $user ): bool {
		return $this->interestArticlesLookup->getInterests( $user ) !== [];
	}

	/**
	 * @param WikiDay $day
	 * @param LinkTarget[] $interests
	 * @return array{recommendations: ReadingRecommendation[], hadSearchError: bool}
	 */
	private function selectRecommendations( WikiDay $day, array $interests ): array {
		$dayNumber = $day->getDayNumber();
		$hadSearchError = false;
		$excluded = [];
		foreach ( $interests as $interest ) {
			$excluded[$interest->getDBkey()] = true;
		}

		$recommendations = [];
		if ( $interests ) {
			$interestCount = count( $interests );
			$offset = $dayNumber % $interestCount;
			for ( $i = 0; $i < min( self::SLOTS, $interestCount ); $i++ ) {
				$interest = $interests[( $offset + $i ) % $interestCount];
				$candidates = $this->getRelatedCandidates( $interest, $hadSearchError );
				$candidateCount = count( $candidates );
				$pick = null;
				for ( $j = 0; $j < $candidateCount; $j++ ) {
					$candidate = $candidates[( $dayNumber + $j ) % $candidateCount];
					if ( !isset( $excluded[$candidate->getDBkey()] ) ) {
						$pick = $candidate;
						break;
					}
				}
				if ( $pick === null ) {
					continue;
				}
				$excluded[$pick->getDBkey()] = true;
				$recommendations[] = new ReadingRecommendation( $pick, $interest );
			}
		}

		if ( count( $recommendations ) < self::SLOTS ) {
			$poolResult = $this->featuredArticlePool->getPool( $day );
			$hadSearchError = $hadSearchError || $poolResult->isError();
			$pool = $poolResult->getTitles();
			$poolCount = count( $pool );
			for ( $i = 0; $i < $poolCount && count( $recommendations ) < self::SLOTS; $i++ ) {
				$candidate = $pool[$i];
				if ( isset( $excluded[$candidate->getDBkey()] ) ) {
					continue;
				}
				$excluded[$candidate->getDBkey()] = true;
				$recommendations[] = new ReadingRecommendation( $candidate );
			}
		}

		return [
			'recommendations' => $recommendations,
			'hadSearchError' => $hadSearchError,
		];
	}

	/**
	 * Related-article candidates for one interest, cached per interest and shared across users,
	 * so every user picks from the identical list regardless of when their list was computed.
	 * A failed search is not cached and is retried on the next request.
	 *
	 * The day is not part of the key: it selects from the list, it does not define it, and the
	 * list only changes when the search index does. A dated key would expire every interest on
	 * the wiki at local midnight at once, and would keep WANObjectCache from refreshing a hot
	 * entry pre-emptively before it expires, because each day would start from a key that has
	 * no predecessor to refresh.
	 *
	 * @param LinkTarget $interest
	 * @param bool &$hadSearchError Set to true when the search failed
	 * @return LinkTarget[]
	 */
	private function getRelatedCandidates( LinkTarget $interest, bool &$hadSearchError ): array {
		$rows = $this->wanCache->getWithSetCallback(
			$this->wanCache->makeKey(
				'growthexperiments-reading-recommendations-related',
				$interest->getDBkey()
			),
			WANObjectCache::TTL_WEEK,
			function ( $oldValue, &$ttl ) use ( $interest, &$hadSearchError ) {
				$searchResult = $this->searcher->findRelated( $interest, self::CANDIDATES_PER_INTEREST );
				if ( $searchResult->isError() ) {
					$hadSearchError = true;
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
				}
				return array_map(
					static fn ( LinkTarget $title ) => [
						'ns' => $title->getNamespace(),
						'dbkey' => $title->getDBkey(),
					],
					$searchResult->getTitles()
				);
			},
			$this->cachePolicy->getCacheOptions( self::CACHE_VERSION )
		);
		return array_map(
			static fn ( array $row ) => new TitleValue( $row['ns'], $row['dbkey'] ),
			$rows
		);
	}
}
