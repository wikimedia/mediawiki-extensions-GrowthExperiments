<?php

namespace GrowthExperiments\UserImpact;

use DateInterval;
use DateTime;
use Language;
use MWTimestamp;
use stdClass;

/**
 * Formats an ExpensiveUserImpact object to be more suitable for frontend use.
 */
class UserImpactFormatter {

	private const PAGEVIEW_TOOL_BASE_URL = 'https://pageviews.wmcloud.org/';

	private stdClass $AQSConfig;
	private Language $contentLanguage;

	/**
	 * @param stdClass $AQSConfig
	 * @param Language $contentLanguage
	 */
	public function __construct(
		stdClass $AQSConfig,
		Language $contentLanguage
	) {
		$this->AQSConfig = $AQSConfig;
		$this->contentLanguage = $contentLanguage;
	}

	/**
	 * Create a new UserImpactFormatter from a serialized ExpensiveUserImpact.
	 * @param array|ExpensiveUserImpact $userImpact
	 * @return array
	 */
	public function format( $userImpact ): array {
		if ( $userImpact instanceof ExpensiveUserImpact ) {
			$jsonData = $userImpact->jsonSerialize();
		} else {
			$jsonData = $userImpact;
		}
		$jsonData += $this->sortAndFilter( $jsonData );
		unset( $jsonData['dailyArticleViews'] );
		$this->fillDailyArticleViewsWithPageViewToolsUrl( $jsonData );
		return $jsonData;
	}

	/**
	 * Calculate the topViewedArticles, topViewedArticlesCount and recentEditsWithoutPageviews
	 * fields by sorting and filtering the daily article views data:
	 * - Get the top 5 most viewed articles, in descending order of views
	 * - Get up to 5 of the most recently edited articles with no page view
	 *   data available yet, in descending order of recency
	 *
	 * @param array $jsonData
	 * @return array
	 */
	private function sortAndFilter( array $jsonData ): array {
		$topViewedArticles = $recentEditsWithoutPageviews = $this->getModifiedDailyArticleViews( $jsonData );

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

		return [
			'recentEditsWithoutPageviews' => $recentEditsWithoutPageviews,
			'topViewedArticles' => $topViewedArticles,
			'topViewedArticlesCount' => $topViewedArticlesCount,
		];
	}

	/**
	 * Returns dailyArticleViews field, with views set to 0 on days before the user's first edit
	 * to the article, and a 'viewsCount' field added to each title with the sum of the
	 * (remaining) views.
	 * @param array $jsonData
	 * @return array
	 */
	private function getModifiedDailyArticleViews( array $jsonData ): array {
		$dailyArticleViews = $jsonData['dailyArticleViews'];
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

	/**
	 * @param array &$jsonData
	 * @return void
	 */
	private function fillDailyArticleViewsWithPageViewToolsUrl(
		array &$jsonData
	): void {
		foreach ( $jsonData['topViewedArticles'] as $title => $articleData ) {
			$latestPageViewDate = array_key_last( $articleData['views'] );
			$jsonData['topViewedArticles'][$title]['pageviewsUrl'] =
				$this->getPageViewToolsUrl( $title, $articleData['firstEditDate'], $latestPageViewDate );
		}
	}

	/**
	 * @param string $title
	 * @param string $firstEditDate Date of the first edit to the article in Y-m-d format.
	 * @param string $latestPageViewDate Date of the most last page view data entry available for this article.
	 *   Used for constructing the 'end' parameter for the URL, to avoid confusion with timezones and what "latest"
	 *   means in the context of the pageviews application and Analytics Query Service.
	 * @return string Full URL for the PageViews tool for the given title and start date
	 */
	private function getPageViewToolsUrl( string $title, string $firstEditDate, string $latestPageViewDate ): string {
		$daysAgo = ComputedUserImpactLookup::PAGEVIEW_DAYS;
		$dtiAgo = new DateTime( '@' . strtotime( "-$daysAgo days", MWTimestamp::time() ) );
		$startDate = $dtiAgo->format( 'Y-m-d' );
		if ( $firstEditDate > $startDate ) {
			$startDate = $firstEditDate;
		}
		return wfAppendQuery( self::PAGEVIEW_TOOL_BASE_URL, [
			'project' => $this->AQSConfig->project,
			'userlang' => $this->contentLanguage->getCode(),
			'start' => $startDate,
			'end' => $latestPageViewDate,
			'pages' => $title,
		] );
	}

}
