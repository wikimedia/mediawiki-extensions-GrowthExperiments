<?php

namespace GrowthExperiments\Tests\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\AddImage\ProductionImageRecommendationApiHandler;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ProductionImageRecommendationApiHandler
 */
class ProductionImageRecommendationApiHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideApiResponseData
	 * @param array $apiResponse API response data.
	 * @param array $expectedResult
	 */
	public function testGetSuggestionDataFromApiResponse( array $apiResponse, array $expectedResult ) {
		$apiHandler = new ProductionImageRecommendationApiHandler(
			$this->createMock( HttpRequestFactory::class ),
			'https://example.com',
			'enwiki',
			null
		);
		$this->assertArrayEquals(
			$expectedResult,
			$apiHandler->getSuggestionDataFromApiResponse( $apiResponse )
		);
	}

	public function provideApiResponseData(): array {
		$suggestions = [
			[
				'wiki' => 'enwiki',
				'page_id' => 344465,
				'id' => '1.23',
				'image' => 'Image1.png',
				'confidence' => 80,
				'found_on' => [ 'enwiki', 'dewiki' ],
				'kind' => [
					'istype-lead-image'
				],
				'origin_wiki' => 'commonswiki',
				'page_rev' => 17463093
			],
			[
				'wiki' => 'enwiki',
				'page_id' => 344465,
				'id' => '1.23',
				'image' => 'Image2.png',
				'confidence' => 80,
				'found_on' => [ 'enwiki' ],
				'kind' => [
					'istype-commons-category'
				],
				'origin_wiki' => 'commonswiki',
				'page_rev' => 17463093
			]
		];
		return [
			'empty rows' => [ [ 'rows' => [] ], [] ],
			'valid response' => [
				[ 'rows' => $suggestions ], [
					new ImageRecommendationData(
						'Image1.png', 'wikipedia', 'enwiki,dewiki', '1.23'
					), new ImageRecommendationData(
						'Image2.png', 'commons', 'enwiki', '1.23'
					)
				]
			],
			'empty details' => [
				[ 'rows' => [
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => null,
						'image' => 'Image1.png',
						'confidence' => 80,
						'found_on' => [],
						'kind' => [],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093
					],
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => null,
						'image' => null,
						'confidence' => 80,
						'found_on' => [],
						'kind' => [],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093
					],
				]
				], [
					new ImageRecommendationData(
						'Image1.png', null, null, null
					),
					new ImageRecommendationData(
						null, null, null, null
					)
				]
			],
			'multiple kinds' => [
				[ 'rows' => [ [
					'wiki' => 'enwiki',
					'page_id' => 344465,
					'id' => '1.23',
					'image' => 'Image1.png',
					'confidence' => 80,
					'found_on' => [],
					'kind' => [
						'istype-wikidata-image',
						'istype-lead-image',
						'istype-commons-category'
					],
					'origin_wiki' => 'commonswiki',
					'page_rev' => 17463093
				] ]
				], [
					new ImageRecommendationData(
						'Image1.png', 'wikidata', '', '1.23'
					)
				]
			]
		];
	}
}
