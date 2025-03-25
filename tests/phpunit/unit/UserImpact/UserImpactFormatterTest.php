<?php

namespace GrowthExperiments\Tests\Unit;

use Exception;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use MediaWiki\Language\Language;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\UserImpact\UserImpactFormatter
 */
class UserImpactFormatterTest extends MediaWikiUnitTestCase {

	/**
	 * @throws Exception
	 */
	public function testTopViewedArticles() {
		$mockLanguage = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$mockLanguage->method( 'getCode' )->willReturn( 'en' );
		$formatter = new UserImpactFormatter( (object)[ 'project' => 'enwiki' ], $mockLanguage );

		$dailyTotalViews = [ '2022-08-24' => 265, '2022-08-25' => 265 ];
		$makeDailyArticleViewField = static function ( $views ) {
			return [
				'firstEditDate' => '2022-08-24',
				'newestEdit' => '20220825100000',
				'views' => $views,
				'imageUrl' => null,
			];
		};
		$pageViewUrl = static function ( string $title, ?string $endDate = null ) {
			$endDate ??= '2022-08-25';
			$url = "https://pageviews.wmcloud.org/?project=enwiki&userlang=en"
				. "&start=2022-08-24&end=$endDate&pages=$title";
			return [ 'pageviewsUrl' => $url ];
		};
		$dailyArticleViews = [
			'Article1' => $makeDailyArticleViewField( [
				'2022-08-24' => 50,
				'2022-08-25' => 50,
				'2022-08-26' => 50,
				'2022-08-27' => 50,
				'2022-08-28' => 50,
				'2022-08-29' => 50,
				'2022-08-30' => 50,
			] ),
			'Article2' => $makeDailyArticleViewField( [ '2022-08-24' => 40, '2022-08-25' => 40 ] ),
			'Article3' => $makeDailyArticleViewField( [ '2022-08-24' => 30, '2022-08-25' => 30 ] ),
			'Article4' => $makeDailyArticleViewField( [ '2022-08-24' => 60, '2022-08-25' => 60 ] ),
			'Article5' => $makeDailyArticleViewField( [ '2022-08-24' => 70, '2022-08-25' => 70 ] ),
			'Article6' => $makeDailyArticleViewField( [ '2022-08-24' => 80, '2022-08-25' => 80 ] ),
			'Article7' => $makeDailyArticleViewField( [ '2022-08-24' => 55, '2022-08-25' => 55 ] ),
			'Article8' => $makeDailyArticleViewField( [ '2022-08-24' => 45, '2022-08-25' => 45 ] ),
			'Article9' => $makeDailyArticleViewField( [ '2022-08-24' => 35, '2022-08-25' => 35 ] ),
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			new EditingStreak(),
			null
		);

		MWTimestamp::setFakeTime( '2022-08-25 12:00:00' );

		$json = $formatter->format( $userImpact, 'en' );
		$expectedTopViewedArticles = [
			'Article1' => $dailyArticleViews['Article1'] + [ 'viewsCount' => 350 ] + $pageViewUrl(
					'Article1', array_key_last( $dailyArticleViews['Article1' ]['views'] )
				),
			'Article6' => $dailyArticleViews['Article6'] + [ 'viewsCount' => 160 ] + $pageViewUrl( 'Article6' ),
			'Article5' => $dailyArticleViews['Article5'] + [ 'viewsCount' => 140 ] + $pageViewUrl( 'Article5' ),
			'Article4' => $dailyArticleViews['Article4'] + [ 'viewsCount' => 120 ] + $pageViewUrl( 'Article4' ),
			'Article7' => $dailyArticleViews['Article7'] + [ 'viewsCount' => 110 ] + $pageViewUrl( 'Article7' ),
		];
		$this->assertSame( $expectedTopViewedArticles, $json['topViewedArticles'] );
		$this->assertSame( 880, $json['topViewedArticlesCount'] );

		MWTimestamp::setFakeTime( '2022-10-28 12:00:00' );
		$json = $formatter->format( $userImpact, 'en' );
		$this->assertSame(
			'https://pageviews.wmcloud.org/?project=enwiki&userlang=en&start=2022-08-29&end=2022-08-30&pages=Article1',
			$json['topViewedArticles']['Article1']['pageviewsUrl']
		);
		$expectedTotalViewsCount = 0;
		foreach ( $dailyArticleViews as $dailyArticleView ) {
			$expectedTotalViewsCount += array_sum( $dailyArticleView['views'] );
		}
		$this->assertSame( $expectedTotalViewsCount, $json['totalPageviewsCount'] );
	}

	public function testRecentEditsWithoutPageviews() {
		$mockLanguage = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$mockLanguage->method( 'getCode' )->willReturn( 'en' );
		$formatter = new UserImpactFormatter( (object)[ 'project' => 'enwiki' ], $mockLanguage );

		$dailyTotalViews = [ '2022-08-15' => 10 ];
		$makeDailyArticleViewField = static function ( $firstEditDay, $newestEditDay, $hasViews ) {
			return [
				'firstEditDate' => "2022-08-$firstEditDay",
				'newestEdit' => "202208{$newestEditDay}100000",
				'views' => $hasViews ? [ '2022-08-15' => 10 ] : [ '2022-08-15' => 0 ],
				'imageUrl' => null,
			];
		};
		$unsetViews = static function ( $dataItem ) {
			unset( $dataItem['views'] );
			return $dataItem;
		};
		$dailyArticleViews = [
			'Article1' => $makeDailyArticleViewField( '16', '20', true ),
			'Article2' => $makeDailyArticleViewField( '16', '30', false ),
			'Article3' => $makeDailyArticleViewField( '15', '21', false ),
			'Article4' => $makeDailyArticleViewField( '16', '29', true ),
			'Article5' => $makeDailyArticleViewField( '16', '22', false ),
			'Article6' => $makeDailyArticleViewField( '15', '28', true ),
			'Article7' => $makeDailyArticleViewField( '16', '15', true ),
			'Article8' => $makeDailyArticleViewField( '16', '31', false ),
			'Article9' => $makeDailyArticleViewField( '15', '25', false ),
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			5,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-14' => 10, '2022-08-15' => 20 ],
			[ 'copyedit' => 10, 'link-recommendation' => 100 ],
			1,
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			new EditingStreak(),
			null
		);

		MWTimestamp::setFakeTime( '2022-08-15 12:00:00' );

		$json = $formatter->format( $userImpact, 'en' );
		$expectedRecentEditsWithoutPageviews = [
			'Article8' => $unsetViews( $dailyArticleViews['Article8'] ),
			'Article2' => $unsetViews( $dailyArticleViews['Article2'] ),
			'Article4' => $unsetViews( $dailyArticleViews['Article4'] ),
			'Article9' => $unsetViews( $dailyArticleViews['Article9'] ),
			'Article5' => $unsetViews( $dailyArticleViews['Article5'] ),
		];
		$this->assertSame( $expectedRecentEditsWithoutPageviews, $json['recentEditsWithoutPageviews'] );
		$this->assertSame( array_sum( $dailyTotalViews ), $json['totalPageviewsCount'] );
	}

}
