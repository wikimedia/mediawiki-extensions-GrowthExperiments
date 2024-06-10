<?php

namespace GrowthExperiments\Tests\Unit;

use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\WarningCollector;
use GrowthExperiments\NewcomerTasks\GrowthArticleTopicFeature;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\GrowthArticleTopicFeature
 */
class GrowthArticleTopicFeatureTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideParseValue
	 */
	public function testParseValue(
		string $value, array $expectedResult, array $expectedWarnings = []
	) {
		$warnings = [];
		$warningCollector = $this->createMock( WarningCollector::class );
		$warningCollector->method( 'addWarning' )->willReturnCallback(
			static function ( $message, ...$params ) use ( &$warnings ) {
				$warnings[] = $message;
			} );
		$feature = new GrowthArticleTopicFeature();
		$result = $feature->parseValue( 'growtharticletopic', $value, $value, '|', '', $warningCollector );
		$this->assertSame( $expectedResult, $result );
		$this->assertSame( $expectedWarnings, $warnings );
	}

	public static function provideParseValue() {
		if ( !class_exists( ArticleTopicFeature::class ) ) {
			self::markTestSkipped( 'depends on CirrusSearch' );
		}
		return [
			'single keyword' => [
				'argentina',
				[
					'topics' => [ 'argentina' ],
					'tag_prefix' => GrowthArticleTopicFeature::TAG_PREFIX,
				],
			],
			'multiple keywords' => [
				'argentina|chile',
				[
					'topics' => [ 'argentina', 'chile' ],
					'tag_prefix' => GrowthArticleTopicFeature::TAG_PREFIX,
				],
			],
			'invalid keyword' => [
				'argentina|crêpe',
				[
					'topics' => [ 'argentina' ],
					'tag_prefix' => GrowthArticleTopicFeature::TAG_PREFIX,
				],
				[ 'growthexperiments-homepage-suggestededits-articletopic-invalid-topic' ],
			],
		];
	}

}
