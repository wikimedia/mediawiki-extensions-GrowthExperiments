<?php

namespace GrowthExperiments\UserImpact;

use DateInterval;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use MWTimestamp;

/**
 * Like ExpensiveUserImpact, but with serialization defined to return top viewed articles and
 * recent edits without page views.
 */
class SortedFilteredUserImpact extends ExpensiveUserImpact {

	/** @var array[]|null lazy-evaluated */
	private ?array $topViewedArticles;

	/** @var array[]|null lazy-evaluated */
	private ?array $recentEditsWithoutPageviews;

	/** @var int|null lazy-evaluated */
	private ?int $topViewedArticlesCount;

	/** @inheritDoc */
	protected static function newEmpty(): self {
		return new SortedFilteredUserImpact(
			new UserIdentityValue( 0, '' ),
			0,
			[],
			[],
			new UserTimeCorrection( 'System|0' ),
			0,
			0,
			[],
			[],
			new EditingStreak()
		);
	}

	/**
	 * An array with the title's prefixed DBkey as the key. The values contain:
	 * - firstEditDate: The date the user first edited the article, in Y-m-d format.
	 * - newestEdit: The TS_MW timestamp of the newest edit by the user to the article.
	 * - views: An array of page view counts. Each row in the array has a key with the date in
	 *   Y-m-d format and the value is the page view count total for that day.
	 * - pageviewsUrl: The URL to use with the pageviews.wmcloud.org service.
	 * @return array[]
	 */
	public function getTopViewedArticles(): array {
		if ( !isset( $this->topViewedArticles ) ) {
			$this->sortAndFilter();
		}
		return $this->topViewedArticles;
	}

	/**
	 * An array with the title's prefixed DBkey as the key. The values contain:
	 * - firstEditDate: The date the user first edited the article, in Y-m-d format.
	 * - newestEdit: The TS_MW timestamp of the newest edit by the user to the article.
	 * - views: An empty array.
	 * - pageviewsUrl: The URL to use with the pageviews.wmcloud.org service.
	 * @return array
	 */
	public function getRecentEditsWithoutPageviews(): array {
		if ( !isset( $this->recentEditsWithoutPageviews ) ) {
			$this->sortAndFilter();
		}
		return $this->recentEditsWithoutPageviews;
	}

	/**
	 * The total views of the top-5-viewed articles together, since the user's first edit to them.
	 * @return int
	 */
	public function getTopViewedArticlesCount(): int {
		if ( !isset( $this->topViewedArticlesCount ) ) {
			$this->sortAndFilter();
		}
		return $this->topViewedArticlesCount;
	}

	/**
	 * Create a new SortedFilteredUserImpact from a serialized ExpensiveUserImpact.
	 * @param array $json
	 * @return self
	 */
	public static function newFromUnsortedJsonArray( array $json ): self {
		$userImpact = self::newEmpty();
		$userImpact->loadFromJsonArray( $json );
		return $userImpact;
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		$jsonData = parent::jsonSerialize();
		unset( $jsonData['dailyArticleViews'] );
		return $jsonData + [
				'recentEditsWithoutPageviews' => $this->getRecentEditsWithoutPageviews(),
				'topViewedArticles' => $this->getTopViewedArticles(),
				'topViewedArticlesCount' => $this->getTopViewedArticlesCount(),
		];
	}

	/**
	 * Calculate the topViewedArticles, topViewedArticlesCount and recentEditsWithoutPageviews
	 * fields by sorting and filtering the daily article views data:
	 * - Get the top 5 most viewed articles, in descending order of views
	 * - Get up to 5 of the most recently edited articles with no page view
	 *   data available yet, in descending order of recency
	 *
	 * @return void
	 */
	private function sortAndFilter(): void {
		$topViewedArticles = $recentEditsWithoutPageviews = $this->getModifiedDailyArticleViews();

		// Order the articles by most views to fewest
		uasort( $topViewedArticles, static function ( $a, $b ) {
			return $b['viewsCount'] <=> $a['viewsCount'];
		} );
		// Get the top five articles in the list that have page views
		$topViewedArticles = array_slice( array_filter( $topViewedArticles, static function ( $item ) {
			return ( $item['viewsCount'] ?? 0 ) > 0;
		} ), 0, 5, true );

		$topViewedArticlesCount = array_sum( array_column( $topViewedArticles, 'viewsCount' ) );

		// Order the articles by date, most recent edit to oldest, and get the most recent 5.
		uasort( $recentEditsWithoutPageviews, static function ( $a, $b ) {
			return $b['newestEdit'] <=> $a['newestEdit'];
		} );
		$twoDaysAgo = MWTimestamp::getInstance()->timestamp
			->sub( new DateInterval( 'P2D' ) )
			->format( 'Y-m-d' );
		// Ignore articles with page views. For articles with 0 in the count,
		// use a proxy of "2 days ago" as an indicator that we don't have page view data yet,
		// since that takes 24-48 hours to generate.
		$recentEditsWithoutPageviews = array_filter( $recentEditsWithoutPageviews,
			static function ( $a ) use ( $twoDaysAgo ) {
				return $a['viewsCount'] === 0 && $a['firstEditDate'] > $twoDaysAgo;
			}
		);
		$recentEditsWithoutPageviews = array_slice( $recentEditsWithoutPageviews, 0, 5, true );
		// Remove the 'viewsCount' key - the frontend will show this as still waiting for pageview data.
		// Also unset 'views' as a micro-optimization.
		foreach ( $recentEditsWithoutPageviews as $title => $_ ) {
			unset( $recentEditsWithoutPageviews[$title]['viewsCount'] );
			unset( $recentEditsWithoutPageviews[$title]['views'] );
		}

		$this->topViewedArticles = $topViewedArticles;
		$this->topViewedArticlesCount = $topViewedArticlesCount;
		$this->recentEditsWithoutPageviews = $recentEditsWithoutPageviews;
	}

	/**
	 * Returns dailyArticleViews field, with views set to 0 on days before the user's first edit
	 * to the article, and a 'viewsCount' field added to each title with the sum of the
	 * (remaining) views.
	 * @return array
	 */
	private function getModifiedDailyArticleViews(): array {
		$dailyArticleViews = $this->getDailyArticleViews();
		foreach ( $dailyArticleViews as $title => $data ) {
			foreach ( $data['views'] as $date => $dailyViews ) {
				if ( $date < $data['firstEditDate'] ) {
					$dailyArticleViews[$title]['views'][$date] = 0;
				}
			}
			$dailyArticleViews[$title]['viewsCount'] = array_sum( $data['views'] );
		}
		return $dailyArticleViews;
	}

}
