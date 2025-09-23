<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\AddImage\ActionApiImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ActionApiImageRecommendationApiHandler
 */
class ActionApiImageRecommendationApiHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideGetSuggestionDataFromApiResponse
	 */
	public function testGetSuggestionDataFromApiResponse( array $apiResponse, $expectedResult ) {
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$apiHandler = new ActionApiImageRecommendationApiHandler(
			$this->createMock( HttpRequestFactory::class ),
			'https://example.com',
			'abcd1234'
		);
		$result = $apiHandler->getSuggestionDataFromApiResponse( $apiResponse, $taskType );
		$this->assertEquals( $expectedResult, $result );
	}

	public static function provideGetSuggestionDataFromApiResponse() {
		return [
			'empty' => [
				'apiResponse' => [
					'query' => [
						'pages' => [],
					],
				],
				'expectedResult' => [],
			],
			'error' => [
				'apiResponse' => [
					'errors' => [
						[
							'code' => 'mustbeloggedin-generic',
							'text' => 'You must be logged in.',
							'module' => 'query+growthimagesuggestiondata',
						],
					],
				],
				'expectedResult' => StatusValue::newFatal(
					new ApiRawMessage( 'You must be logged in.', 'mustbeloggedin-generic' )
				),
			],
			'errors2' => [
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'pageid' => 1,
								'ns' => 0,
								'title' => 'Foo',
								'growthimagesuggestiondataerrors' => [
									[
										'code' => 'growthexperiments-no-recommendation-found',
										'text' => 'No recommendation found for page: Foo',
									],
								],
							],
						],
					],
				],
				'expectedResult' => StatusValue::newFatal(
					new ApiRawMessage( 'No recommendation found for page: Foo',
						'growthexperiments-no-recommendation-found' )
				),
			],
			'success' => [
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'pageid' => 1,
								'ns' => 0,
								'title' => 'Foo',
								'growthimagesuggestiondata' => [
									[
										'titleNamespace' => 0,
										'titleText' => 'Foo',
										'images' => [
											[
												'image' => 'Foo.jpg',
												'displayFilename' => 'Foo.jpg',
												'source' => 'commons',
												'projects' => [ 'enwiki' ],
												'metadata' => [ '...' ],
												'sectionNumber' => null,
												'sectionTitle' => null,
											],
										],
										'datasetId' => '1234abcd',
									],
								],
							],
						],
					],
				],
				'expectedResult' => [
					new ImageRecommendationData( 'Foo.jpg', 'commons', 'enwiki', '1234abcd' ),
				],
			],
			'multiple sources' => [
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'pageid' => 1,
								'ns' => 0,
								'title' => 'Foo',
								'growthimagesuggestiondata' => [
									[
										'titleNamespace' => 0,
										'titleText' => 'Foo',
										'images' => [
											[
												'image' => 'Foo.jpg',
												'displayFilename' => 'Foo.jpg',
												'source' => 'commons',
												'projects' => [ 'enwiki', 'dewiki' ],
												'metadata' => [ '...' ],
												'sectionNumber' => null,
												'sectionTitle' => null,
											],
										],
										'datasetId' => '1234abcd',
									],
								],
							],
						],
					],
				],
				'expectedResult' => [
					new ImageRecommendationData( 'Foo.jpg', 'commons', 'enwiki,dewiki', '1234abcd' ),
				],
			],
			'multiple images' => [
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'pageid' => 1,
								'ns' => 0,
								'title' => 'Foo',
								'growthimagesuggestiondata' => [
									[
										'titleNamespace' => 0,
										'titleText' => 'Foo',
										'images' => [
											[
												'image' => 'Foo.jpg',
												'displayFilename' => 'Foo.jpg',
												'source' => 'commons',
												'projects' => [ 'enwiki' ],
												'metadata' => [ '...' ],
												'sectionNumber' => null,
												'sectionTitle' => null,
											],
											[
												'image' => 'Bar.jpg',
												'displayFilename' => 'Bar.jpg',
												'source' => 'commons',
												'projects' => [ 'dewiki' ],
												'metadata' => [ '...' ],
												'sectionNumber' => null,
												'sectionTitle' => null,
											],
										],
										'datasetId' => '1234abcd',
									],
								],
							],
						],
					],
				],
				'expectedResult' => [
					new ImageRecommendationData( 'Foo.jpg', 'commons', 'enwiki', '1234abcd' ),
					new ImageRecommendationData( 'Bar.jpg', 'commons', 'dewiki', '1234abcd' ),
				],
			],
			'filter by source' => [
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'pageid' => 1,
								'ns' => 0,
								'title' => 'Foo',
								'growthimagesuggestiondata' => [
									[
										'titleNamespace' => 0,
										'titleText' => 'Foo',
										'images' => [
											[
												'image' => 'Foo.jpg',
												'displayFilename' => 'Foo.jpg',
												'source' => 'commons',
												'projects' => [ 'enwiki' ],
												'metadata' => [ '...' ],
												'sectionNumber' => null,
												'sectionTitle' => null,
											],
											[
												'image' => 'Bar.jpg',
												'displayFilename' => 'Bar.jpg',
												'source' => 'wikidata-section',
												'projects' => [],
												'metadata' => [ '...' ],
												'sectionNumber' => 2,
												'sectionTitle' => 'Foo',
											],
										],
										'datasetId' => '1234abcd',
									],
								],
							],
						],
					],
				],
				'expectedResult' => [
					new ImageRecommendationData( 'Foo.jpg', 'commons', 'enwiki', '1234abcd' ),
				],
			],
			// only needed to work until d78543cb reaches production
			'no section data' => [
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'pageid' => 1,
								'ns' => 0,
								'title' => 'Foo',
								'growthimagesuggestiondata' => [
									[
										'titleNamespace' => 0,
										'titleText' => 'Foo',
										'images' => [
											[
												'image' => 'Foo.jpg',
												'displayFilename' => 'Foo.jpg',
												'source' => 'commons',
												'projects' => [ 'enwiki' ],
												'metadata' => [ '...' ],
											],
										],
										'datasetId' => '1234abcd',
									],
								],
							],
						],
					],
				],
				'expectedResult' => [
					new ImageRecommendationData( 'Foo.jpg', 'commons', 'enwiki', '1234abcd' ),
				],
			],
			'source alias' => [
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'pageid' => 1,
								'ns' => 0,
								'title' => 'Foo',
								'growthimagesuggestiondata' => [
									[
										'titleNamespace' => 0,
										'titleText' => 'Foo',
										'images' => [
											[
												'image' => 'Foo.jpg',
												'displayFilename' => 'Foo.jpg',
												'source' => 'wikidata-section',
												'projects' => [ 'enwiki' ],
												'metadata' => [ '...' ],
												'sectionNumber' => null,
												'sectionTitle' => null,
											],
										],
										'datasetId' => '1234abcd',
									],
								],
							],
						],
					],
				],
				'expectedResult' => [
					new ImageRecommendationData( 'Foo.jpg', 'wikidata-section-topics', 'enwiki', '1234abcd' ),
				],
			],
		];
	}

}
