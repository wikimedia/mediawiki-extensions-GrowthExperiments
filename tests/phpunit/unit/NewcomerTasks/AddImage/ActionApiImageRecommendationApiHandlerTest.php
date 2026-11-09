<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\AddImage\ActionApiImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Http\MWHttpRequest;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ActionApiImageRecommendationApiHandler
 */
class ActionApiImageRecommendationApiHandlerTest extends MediaWikiUnitTestCase {

	public function testGetApiRequest() {
		$request = $this->createMock( MWHttpRequest::class );
		$request->expects( $this->once() )
			->method( 'setHeader' )
			->with( 'Authorization', 'Bearer abcd1234' );
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->expects( $this->once() )
			->method( 'create' )
			->with( $this->stringStartsWith( 'https://example.com/w/api.php?action=query' ) )
			->willReturn( $request );
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'Foo' );
		$apiHandler = new ActionApiImageRecommendationApiHandler(
			$this->createNoOpMock( StatusFormatter::class ),
			$httpRequestFactory,
			new NullLogger(),
			'https://example.com/w/api.php',
			'abcd1234'
		);

		$result = $apiHandler->getApiRequest(
			$title,
			new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY )
		);

		$this->assertSame( $request, $result );
	}

	/**
	 * @dataProvider provideMissingConfiguration
	 */
	public function testGetApiRequestWithMissingConfiguration( ?string $apiUrl, ?string $accessToken ) {
		$apiHandler = new ActionApiImageRecommendationApiHandler(
			$this->createNoOpMock( StatusFormatter::class ),
			$this->createNoOpMock( HttpRequestFactory::class ),
			new NullLogger(),
			$apiUrl,
			$accessToken
		);

		$result = $apiHandler->getApiRequest(
			$this->createNoOpMock( Title::class ),
			new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY )
		);

		$this->assertInstanceOf( StatusValue::class, $result );
		$this->assertStatusError( 'rawmessage', $result );
	}

	public static function provideMissingConfiguration(): array {
		return [
			'null access token' => [ 'https://example.com/w/api.php', null ],
			'empty access token' => [ 'https://example.com/w/api.php', '' ],
			'null URL' => [ null, 'abcd1234' ],
			'empty URL' => [ '', 'abcd1234' ],
		];
	}

	/**
	 * @dataProvider provideGetSuggestionDataFromApiResponse
	 */
	public function testGetSuggestionDataFromApiResponse( array $apiResponse, $expectedResult ) {
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$apiHandler = new ActionApiImageRecommendationApiHandler(
			$this->createNoOpMock( StatusFormatter::class ),
			$this->createMock( HttpRequestFactory::class ),
			new NullLogger(),
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
