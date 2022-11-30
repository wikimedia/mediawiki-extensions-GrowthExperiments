<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use Language;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use MediaWikiUnitTestCase;
use MWTimestamp;

/**
 * @covers \GrowthExperiments\UserImpact\UserImpactFormatter
 */
class UserImpactFormatterTest extends MediaWikiUnitTestCase {

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
			$endDate = $endDate ?? '2022-08-25';
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
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			new UserTimeCorrection( 'System|0' ),
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			new EditingStreak()
		);

		MWTimestamp::setFakeTime( '2022-08-25 12:00:00' );

		$json = $formatter->format( $userImpact );
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
		$json = $formatter->format( $userImpact );
		$this->assertSame(
			'https://pageviews.wmcloud.org/?project=enwiki&userlang=en&start=2022-08-29&end=2022-08-30&pages=Article1',
			$json['topViewedArticles']['Article1']['pageviewsUrl']
		);
	}

	public function testRecentEditsWithoutPageviews() {
		$mockLanguage = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$mockLanguage->method( 'getCode' )->willReturn( 'en' );
		$formatter = new UserImpactFormatter( (object)[ 'project' => 'enwiki' ], $mockLanguage );

		$dailyTotalViews = [ '2022-08-25' => 10 ];
		$makeDailyArticleViewField = static function ( $firstEditDay, $newestEditDay, $hasViews ) {
			return [
				'firstEditDate' => "2022-08-$firstEditDay",
				'newestEdit' => "202208{$newestEditDay}100000",
				'views' => $hasViews ? [ '2022-08-25' => 10 ] : [ '2022-08-25' => 0 ],
				'imageUrl' => null,
			];
		};
		$unsetViews = static function ( $dataItem ) {
			unset( $dataItem['views'] );
			return $dataItem;
		};
		$dailyArticleViews = [
			'Article1' => $makeDailyArticleViewField( '24', '10', true ),
			'Article2' => $makeDailyArticleViewField( '24', '20', false ),
			'Article3' => $makeDailyArticleViewField( '22', '11', false ),
			'Article4' => $makeDailyArticleViewField( '24', '19', true ),
			'Article5' => $makeDailyArticleViewField( '24', '12', false ),
			'Article6' => $makeDailyArticleViewField( '22', '18', false ),
			'Article7' => $makeDailyArticleViewField( '24', '05', true ),
			'Article8' => $makeDailyArticleViewField( '24', '25', false ),
			'Article9' => $makeDailyArticleViewField( '22', '15', false ),
		];
		$userImpact = new ExpensiveUserImpact(
			UserIdentityValue::newRegistered( 1, 'User1' ),
			10,
			[ NS_MAIN => 100, NS_TALK => 10, NS_USER_TALK => 15 ],
			[ '2022-08-24' => 10, '2022-08-25' => 20 ],
			new UserTimeCorrection( 'System|0' ),
			80,
			wfTimestamp( TS_UNIX, '20200101000000' ),
			$dailyTotalViews,
			$dailyArticleViews,
			new EditingStreak()
		);

		MWTimestamp::setFakeTime( '2022-08-25 12:00:00' );

		$json = $formatter->format( $userImpact );
		$expectedRecentEditsWithoutPageviews = [
			'Article8' => $unsetViews( $dailyArticleViews['Article8'] ),
			'Article2' => $unsetViews( $dailyArticleViews['Article2'] ),
			'Article5' => $unsetViews( $dailyArticleViews['Article5'] ),
		];
		$this->assertSame( $expectedRecentEditsWithoutPageviews, $json['recentEditsWithoutPageviews'] );
	}

}
