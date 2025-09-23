<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\AddImage\ProductionImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use Wikimedia\UUID\GlobalIdGenerator;

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
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$globalIdGenerator = $this->createMock( GlobalIdGenerator::class );
		$globalIdGenerator->method( 'getTimestampFromUUIDv1' )->willReturnCallback(
			static function ( $id ) {
				return $id;
			}
		);
		$apiHandler = new ProductionImageRecommendationApiHandler(
			$this->createMock( HttpRequestFactory::class ),
			'https://example.com',
			'enwiki',
			$globalIdGenerator,
			null
		);
		$this->assertArrayEquals(
			$expectedResult,
			$apiHandler->getSuggestionDataFromApiResponse( $apiResponse, $taskType )
		);
	}

	public static function provideApiResponseData(): array {
		$suggestions = [
			[
				'wiki' => 'enwiki',
				'page_id' => 344465,
				'id' => '1.23',
				'image' => 'Image1.png',
				'confidence' => 90,
				'found_on' => [ 'enwiki', 'dewiki' ],
				'kind' => [
					'istype-lead-image',
				],
				'origin_wiki' => 'commonswiki',
				'page_rev' => 17463093,
				'section_index' => null,
				'section_heading' => null,
			],
			[
				'wiki' => 'enwiki',
				'page_id' => 344465,
				'id' => '1.23',
				'image' => 'Image2.png',
				'confidence' => 80,
				'found_on' => [ 'enwiki' ],
				'kind' => [
					'istype-commons-category',
				],
				'origin_wiki' => 'commonswiki',
				'page_rev' => 17463093,
				'section_index' => null,
				'section_heading' => null,
			],
			[
				'wiki' => 'enwiki',
				'page_id' => 344465,
				'id' => '1.23',
				'image' => 'Image3.png',
				'confidence' => 50,
				'found_on' => [],
				'kind' => [
					'istype-commons-category',
				],
				'origin_wiki' => 'commonswiki',
				'page_rev' => 17463093,
				'section_index' => null,
				'section_heading' => null,
			],
			[
				'wiki' => 'enwiki',
				'page_id' => 344465,
				'id' => '1.23',
				'image' => 'Image4.png',
				'confidence' => 40,
				'found_on' => null,
				'kind' => [
					'istype-commons-category',
				],
				'origin_wiki' => 'commonswiki',
				'page_rev' => 17463093,
				'section_index' => null,
				'section_heading' => null,
			],
		];
		return [
			'empty rows' => [ [ 'rows' => [] ], [] ],
			'valid response' => [
				[ 'rows' => $suggestions ], [
					new ImageRecommendationData(
						'Image1.png', 'wikipedia', 'enwiki,dewiki', '1.23'
					),
					new ImageRecommendationData(
						'Image2.png', 'commons', 'enwiki', '1.23'
					),
					new ImageRecommendationData(
						'Image3.png', 'commons', '', '1.23'
					),
					new ImageRecommendationData(
						'Image4.png', 'commons', '', '1.23'
					),
				],
			],
			'sort response by confidence' => [
				[ 'rows' => [
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '1',
						'image' => 'confidence80.png',
						'confidence' => 80,
						'found_on' => [ 'eswiki' ],
						'kind' => [
							'istype-lead-image',
						],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '1',
						'image' => 'confidence90.png',
						'confidence' => 90,
						'found_on' => [ 'eswiki' ],
						'kind' => [
							'istype-lead-image',
						],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
				] ], [
					new ImageRecommendationData(
						'confidence90.png', 'wikipedia', 'eswiki', '1'
					),
					new ImageRecommendationData(
						'confidence80.png', 'wikipedia', 'eswiki', '1'
					),
				],
			],
			'discard old dataset' => [
				[ 'rows' => [
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '1',
						'image' => 'confidence80.png',
						'confidence' => 80,
						'found_on' => [ 'eswiki' ],
						'kind' => [
							'istype-lead-image',
						],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '2',
						'image' => 'confidence80-1.png',
						'confidence' => 80,
						'found_on' => [ 'eswiki' ],
						'kind' => [
							'istype-lead-image',
						],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
				] ], [
					new ImageRecommendationData(
						'confidence80-1.png', 'wikipedia', 'eswiki', '2'
					),
				],
			],
			'sort response by confidence and discard old dataset' => [
				[ 'rows' => [
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '1',
						'image' => 'confidence90.png',
						'confidence' => 90,
						'found_on' => [ 'eswiki' ],
						'kind' => [
							'istype-lead-image',
						],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '2',
						'image' => 'confidence80.png',
						'confidence' => 80,
						'found_on' => [ 'eswiki' ],
						'kind' => [
							'istype-lead-image',
						],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '2',
						'image' => 'confidence90.png',
						'confidence' => 90,
						'found_on' => [ 'eswiki' ],
						'kind' => [
							'istype-lead-image',
						],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
				] ], [
					new ImageRecommendationData(
						'confidence90.png', 'wikipedia', 'eswiki', '2'
					),
					new ImageRecommendationData(
						'confidence80.png', 'wikipedia', 'eswiki', '2'
					),
				],
			],
			'empty details' => [
				[ 'rows' => [
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '1',
						'image' => 'Image1.png',
						'confidence' => 80,
						'found_on' => [],
						'kind' => [],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
					[
						'wiki' => 'enwiki',
						'page_id' => 344465,
						'id' => '1',
						'image' => null,
						'confidence' => 80,
						'found_on' => [],
						'kind' => [],
						'origin_wiki' => 'commonswiki',
						'page_rev' => 17463093,
						'section_index' => null,
						'section_heading' => null,
					],
				] ], [
					new ImageRecommendationData(
						'Image1.png', 'wikidata', '', '1'
					),
					new ImageRecommendationData(
						null, 'wikidata', '', '1'
					),
				],
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
						'istype-commons-category',
					],
					'origin_wiki' => 'commonswiki',
					'page_rev' => 17463093,
					'section_index' => null,
					'section_heading' => null,
				] ] ], [
					new ImageRecommendationData(
						'Image1.png', 'wikidata', '', '1.23'
					),
				],
			],
			'unknown kinds' => [
				[ 'rows' => [ [
					'wiki' => 'enwiki',
					'page_id' => 344465,
					'id' => '1.23',
					'image' => 'Image1.png',
					'confidence' => 80,
					'found_on' => [],
					'kind' => [
						'istype-unknown',
						'istype-other-unknown',
					],
					'origin_wiki' => 'commonswiki',
					'page_rev' => 17463093,
					'section_index' => null,
					'section_heading' => null,
				] ] ], [
					new ImageRecommendationData(
						'Image1.png', 'wikidata', '', '1.23'
					),
				],
			],
			'known and unknown kinds' => [
				[ 'rows' => [ [
					'wiki' => 'enwiki',
					'page_id' => 344465,
					'id' => '1.23',
					'image' => 'Image1.png',
					'confidence' => 80,
					'found_on' => [ 'enwiki' ],
					'kind' => [
						'istype-unknown',
						'istype-other-unknown',
						'istype-lead-image',
					],
					'origin_wiki' => 'commonswiki',
					'page_rev' => 17463093,
					'section_index' => null,
					'section_heading' => null,
				] ] ], [
					new ImageRecommendationData(
						'Image1.png', 'wikipedia', 'enwiki', '1.23'
					),
				],
			],
			'has section' => [
				[ 'rows' => [ [
					'wiki' => 'enwiki',
					'page_id' => 344465,
					'id' => '1.23',
					'image' => 'Image1.png',
					'confidence' => 90,
					'found_on' => [ 'enwiki', 'dewiki' ],
					'kind' => [
						'istype-lead-image',
					],
					'origin_wiki' => 'commonswiki',
					'page_rev' => 17463093,
					'section_index' => 1,
					'section_heading' => 'Foo',
				] ] ], [],
			],
		];
	}

	/**
	 * @dataProvider provideApiResponseData_section
	 * @param array $apiResponse API response data.
	 * @param array $expectedResult
	 */
	public function testGetSuggestionDataFromApiResponse_section( array $apiResponse, array $expectedResult ) {
		$taskType = new ImageRecommendationTaskType( 'section-image-recommendation', TaskType::DIFFICULTY_EASY );
		$globalIdGenerator = $this->createMock( GlobalIdGenerator::class );
		$globalIdGenerator->method( 'getTimestampFromUUIDv1' )->willReturnCallback(
			static function ( $id ) {
				return $id;
			}
		);
		$apiHandler = new ProductionImageRecommendationApiHandler(
			$this->createMock( HttpRequestFactory::class ),
			'https://example.com',
			'enwiki',
			$globalIdGenerator,
			null
		);
		$this->assertArrayEquals(
			$expectedResult,
			$apiHandler->getSuggestionDataFromApiResponse( $apiResponse, $taskType )
		);
	}

	public static function provideApiResponseData_section() {
		return [
			'section' => [
				[ 'rows' => [ [
					'wiki' => 'enwiki',
					'page_id' => 344465,
					'id' => '1.23',
					'image' => 'Image1.png',
					'confidence' => 80,
					'found_on' => [],
					'kind' => [
						'istype-section-topics-p18',
					],
					'origin_wiki' => 'wikidatawiki',
					'page_rev' => 17463093,
					'section_index' => 2,
					'section_heading' => 'Foo',
				] ] ], [
					new ImageRecommendationData(
						'Image1.png', 'wikidata-section-topics', '', '1.23', 2, 'Foo'
					),
				],
			],
			'section w/ intersection' => [
				[ 'rows' => [ [
					'wiki' => 'enwiki',
					'page_id' => 344465,
					'id' => '1.23',
					'image' => 'Image1.png',
					'confidence' => 80,
					'found_on' => [ 'enwiki', 'cswiki' ],
					'kind' => [
						'istype-section-topics',
						'istype-section-alignment',
					],
					'origin_wiki' => 'wikidatawiki',
					'page_rev' => 17463093,
					'section_index' => 2,
					'section_heading' => 'Foo',
				] ] ], [
					new ImageRecommendationData(
						'Image1.png', 'wikidata-section-intersection', 'enwiki,cswiki', '1.23', 2, 'Foo'
					),
				],
			],
		];
	}

}
