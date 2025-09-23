<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\AddImage\MvpImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\MvpImageRecommendationApiHandler
 */
class MvpImageRecommendationApiHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideApiResponseData
	 * @param array $apiResponse API response data.
	 * @param array $expectedResult
	 */
	public function testGetSuggestionDataFromApiResponse( array $apiResponse, array $expectedResult ) {
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$apiHandler = new MvpImageRecommendationApiHandler(
			$this->createMock( HttpRequestFactory::class ),
			'https://example.com',
			'wikipedia',
			'en',
			null,
			null,
			true
		);
		$this->assertArrayEquals(
			$expectedResult,
			$apiHandler->getSuggestionDataFromApiResponse( $apiResponse, $taskType )
		);
	}

	public static function provideApiResponseData(): array {
		$suggestions = [
			[
				'filename' => 'Image1.png',
				'source' => [
					'details' => [
						'from' => 'wikipedia',
						'found_on' => 'enwiki,dewiki',
						'dataset_id' => '1.23',
					],
				],
			],
			[
				'filename' => 'Image2.png',
				'source' => [
					'details' => [
						'from' => 'wikipedia',
						'found_on' => 'enwiki',
						'dataset_id' => '1.23',
					],
				],
			],
		];
		return [
			'empty pages' => [ [ 'pages' => [] ], [] ],
			'empty suggestions' => [ [ 'pages' => [ [ 'suggestions' => [] ] ] ], [] ],
			'valid response' => [
				[ 'pages' => [ [ 'suggestions' => $suggestions ] ] ], [
					new ImageRecommendationData(
					'Image1.png', 'wikipedia', 'enwiki,dewiki', '1.23'
					), new ImageRecommendationData(
						'Image2.png', 'wikipedia', 'enwiki', '1.23'
					),
				],
			],
			'sort by confidence rating' => [
				[ 'pages' => [ [
					'suggestions' => [
						[
							'filename' => 'high_confidence.png',
							'confidence_rating' => 'medium',
							'source' => [],
						],
						[
							'filename' => 'low_confidence.png',
							'confidence_rating' => 'low',
							'source' => [],
						],
						[
							'filename' => 'medium_confidence.png',
							'confidence_rating' => 'high',
							'source' => [],
						],
					],
				] ] ], [
					new ImageRecommendationData(
						'high_confidence.png', null, null, null
					),
					new ImageRecommendationData(
						'medium_confidence.png', null, null, null
					),
					new ImageRecommendationData(
						'low_confidence.png', null, null, null
					),
				],
			],
			'empty details' => [
				[ 'pages' => [ [
					'suggestions' => [
						[
							'filename' => 'Image1.png',
							'source' => [],
						],
						[
							'source' => [],
						],
					],
				] ] ], [
					new ImageRecommendationData(
					'Image1.png', null, null, null
					),
					new ImageRecommendationData(
						null, null, null, null
					),
				],
			],
		];
	}
}
