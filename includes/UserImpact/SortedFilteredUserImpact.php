<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;

/**
 * Like ExpensiveUserImpact, but with serialization defined to return top viewed articles and recent edits without
 * page views.
 */
class SortedFilteredUserImpact extends ExpensiveUserImpact {

	/**
	 * @var array An array with the title's prefixed DBkey as the key. The values contain:
	 * - firstEditDate: The date the user first edited the article, in Y-m-d format.
	 * - newestEdit: The TS_MW timestamp of the newest edit by the user to the article.
	 * - views: An array of page view counts. Each row in the array has a key with the date in Y-m-d format and
	 *   the value is the page view count total for that day.
	 * - pageviewsUrl: The URL to use with the pageviews.wmcloud.org service.
	 */
	private array $topViewedArticles;

	/**
	 * @var array An array with the title's prefixed DBkey as the key. The values contain:
	 * - firstEditDate: The date the user first edited the article, in Y-m-d format.
	 * - newestEdit: The TS_MW timestamp of the newest edit by the user to the article.
	 * - views: An empty array.
	 * - pageviewsUrl: The URL to use with the pageviews.wmcloud.org service.
	 */
	private array $recentEditsWithoutPageviews;

	/** @inheritDoc */
	public function __construct(
		UserIdentity $user,
		int $receivedThanksCount,
		array $editCountByNamespace,
		array $editCountByDay,
		UserTimeCorrection $timeZone,
		int $newcomerTaskEditCount,
		?int $lastEditTimestamp,
		array $dailyTotalViews,
		array $dailyArticleViews,
		EditingStreak $longestEditingStreak,
		array $topViewedArticles,
		array $recentEditsWithoutPageviews
	) {
		parent::__construct(
			$user,
			$receivedThanksCount,
			$editCountByNamespace,
			$editCountByDay,
			$timeZone,
			$newcomerTaskEditCount,
			$lastEditTimestamp,
			$dailyTotalViews,
			$dailyArticleViews,
			$longestEditingStreak
		);
		$this->topViewedArticles = $topViewedArticles;
		$this->recentEditsWithoutPageviews = $recentEditsWithoutPageviews;
	}

	/** @inheritDoc */
	protected static function newEmpty(): UserImpact {
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
			new EditingStreak(),
			[],
			[]
		);
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): UserImpact {
		$userImpact = self::newEmpty();
		$userImpact->loadFromJsonArray( $json );
		return $userImpact;
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		$jsonData = parent::jsonSerialize();
		$this->sortAndFilter( $jsonData );
		unset( $jsonData['dailyArticleViews'] );
		return $jsonData + [
				'recentEditsWithoutPageviews' => $this->recentEditsWithoutPageviews,
				'topViewedArticles' => $this->topViewedArticles
		];
	}

	/**
	 * Sort and filter the daily article views data:
	 * - Get the top 5 most viewed articles, in descending order of views
	 * - Get up to 5 of the most recently edited articles with no page view
	 *   data available yet, in descending order of recency
	 *
	 * @param array $jsonData
	 * @return void
	 */
	private function sortAndFilter( array $jsonData ): void {
		$dailyArticleViews = $jsonData['dailyArticleViews'];
		// Order the articles by most views to fewest and get the top 5.
		uasort( $dailyArticleViews, static function ( $a, $b ) {
			return array_sum( $b['views'] ) <=> array_sum( $a['views'] );
		} );
		$topViewedArticles = array_slice( $dailyArticleViews, 0, 5, true );

		// Order the articles by date, most recent edit to oldest, and get the most recent 5.
		$recentEditsWithoutPageviews = [];
		$dailyArticleViews = $jsonData['dailyArticleViews'];
		uasort( $dailyArticleViews, static function ( $a, $b ) {
			return $b['newestEdit'] <=> $a['newestEdit'];
		} );
		$dtiAgo = strtotime( "-2 days" );
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
				strtotime( $data['firstEditDate'] ) > $dtiAgo ) {
				$data['views'] = null;
				$recentEditsWithoutPageviews[$title] = $data;
			}
		}
		$this->topViewedArticles = $topViewedArticles;
		$this->recentEditsWithoutPageviews = $recentEditsWithoutPageviews;
	}

}
