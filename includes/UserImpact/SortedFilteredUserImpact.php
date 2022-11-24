<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use MWTimestamp;

/**
 * Like ExpensiveUserImpact, but with serialization defined to return top viewed articles and
 * recent edits without page views.
 */
class SortedFilteredUserImpact extends ExpensiveUserImpact {

	/** @var array[]|null lazy-evaluated */
	private ?array $topViewedArticles = null;

	/** @var array[]|null lazy-evaluated */
	private ?array $recentEditsWithoutPageviews = null;

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
		];
	}

	/**
	 * Calculate the topViewedArticles and recentEditsWithoutPageviews fields by sorting
	 * and filtering the daily article views data:
	 * - Get the top 5 most viewed articles, in descending order of views
	 * - Get up to 5 of the most recently edited articles with no page view
	 *   data available yet, in descending order of recency
	 *
	 * @return void
	 */
	private function sortAndFilter(): void {
		$dailyArticleViews = $this->getDailyArticleViews();
		// Order the articles by most views to fewest and get the top 5.
		uasort( $dailyArticleViews, static function ( $a, $b ) {
			return array_sum( $b['views'] ) <=> array_sum( $a['views'] );
		} );
		$topViewedArticles = array_slice( $dailyArticleViews, 0, 5, true );

		// Order the articles by date, most recent edit to oldest, and get the most recent 5.
		$recentEditsWithoutPageviews = [];
		$dailyArticleViews = $this->getDailyArticleViews();
		uasort( $dailyArticleViews, static function ( $a, $b ) {
			return $b['newestEdit'] <=> $a['newestEdit'];
		} );
		$dtiAgo = strtotime( "-2 days", MWTimestamp::time() );
		foreach ( $dailyArticleViews as $title => $data ) {
			if ( count( $recentEditsWithoutPageviews ) >= 5 ) {
				break;
			}
			// Ignore articles with page views.
			if ( isset( $data['views'] ) && is_array( $data['views'] ) && array_sum( $data['views'] ) > 0 ) {
				continue;
			}
			// For articles with 0 in the count, use a proxy of "2 days ago" as an indicator
			// that we don't have page view data yet, since that takes 24-48 hours to generate.
			if ( isset( $data['firstEditDate'] ) &&
				is_string( $data['firstEditDate'] ) &&
				strtotime( $data['firstEditDate'], MWTimestamp::time() ) > $dtiAgo
			) {
				$data['views'] = null;
				$recentEditsWithoutPageviews[$title] = $data;
			}
		}
		$this->topViewedArticles = $topViewedArticles;
		$this->recentEditsWithoutPageviews = $recentEditsWithoutPageviews;
	}

}
