<?php

namespace GrowthExperiments\Tests;

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

	public function provideParseValue() {
		if ( !class_exists( ArticleTopicFeature::class ) ) {
			$this->markTestSkipped( 'depends on CirrusSearch' );
		}
		return [
			'argentina accepted' => [
				'argentina',
				[
					'topics' => [
						GrowthArticleTopicFeature::TERMS_TO_LABELS['argentina'],
					],
					'tag_prefix' => GrowthArticleTopicFeature::TAG_PREFIX,
				],
			],
			'articletopic topics not accepted' => [
				'media|women|argentina',
				[
					'topics' => [
						GrowthArticleTopicFeature::TERMS_TO_LABELS['argentina'],
					],
					'tag_prefix' => GrowthArticleTopicFeature::TAG_PREFIX,
				],
				[ 'growthexperiments-homepage-suggestededits-articletopic-invalid-topic' ],
			],
		];
	}

}
