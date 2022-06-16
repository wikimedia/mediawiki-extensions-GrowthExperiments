<?php

namespace GrowthExperiments\Tests\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\AddImage\OldImageRecommendationApiHandler;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\OldImageRecommendationApiHandler
 */
class OldImageRecommendationApiHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideApiResponseData
	 * @param array $apiResponse API response data.
	 * @param array $expectedResult
	 */
	public function testGetSuggestionDataFromApiResponse( array $apiResponse, array $expectedResult ) {
		$apiHandler = new OldImageRecommendationApiHandler(
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
			$apiHandler->getSuggestionDataFromApiResponse( $apiResponse )
		);
	}

	public function provideApiResponseData(): array {
		$suggestions = [
			[
				'filename' => 'Image1.png',
				'source' => [
					'details' => [
						'from' => 'wikipedia',
						'found_on' => 'enwiki,dewiki',
						'dataset_id' => '1.23'
					]
				]
			],
			[
				'filename' => 'Image2.png',
				'source' => [
					'details' => [
						'from' => 'wikipedia',
						'found_on' => 'enwiki',
						'dataset_id' => '1.23'
					]
				]
			]
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
					)
				]
			],
			'empty details' => [
				[ 'pages' => [ [
					'suggestions' => [
						[
							'filename' => 'Image1.png',
							'source' => []
						],
						[
							'source' => []
						]
					]
				] ] ], [
					new ImageRecommendationData(
					'Image1.png', null, null, null
					),
					new ImageRecommendationData(
						null, null, null, null
					)
				]
			],
		];
	}
}
