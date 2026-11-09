<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationImage;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ProductionImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Http\MWHttpRequest;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use StatusValue;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Stats\StatsUtils;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider
 */
class ServiceImageRecommendationProviderTest extends MediaWikiIntegrationTestCase {
	// These should really be unit tests but must be declared as integration tests because
	// File::normalizeTitle() cannot be mocked and has all kinds of dependencies :(

	use MockTitleTrait;

	private const SUGGESTIONS_URL = 'http://example.com/public/image_suggestions/suggestions/enwiki/';
	// The production handler sorts by the timestamp in the dataset ID. Thus it must be a UUIDv1.
	private const DATASET_ID = '784fca88-da30-11f1-84d7-e43d1a540f8e';

	/**
	 * @covers ::get
	 * @covers ::processApiResponseData
	 */
	public function testGet() {
		$titleFactory = $this->getTitleFactory();
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$metadataProvider->method( 'getMetadata' )->willReturn( self::metadataFactory() );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$apiHandler = $this->getApiHandler( [
			self::SUGGESTIONS_URL . '10' => [ 400, [] ],
			self::SUGGESTIONS_URL . '11' => [ 200, '{{{' ],
			self::SUGGESTIONS_URL . '12' => [ 200, [ 'rows' => [] ] ],
			self::SUGGESTIONS_URL . '13' => [ 200, [ 'rows' => [
				self::makeRow( '13.png', 'istype-section-topics', [], 'Section', 1 ),
			] ] ],
			self::SUGGESTIONS_URL . '14' => [ 200, [ 'rows' => [
				self::makeRow( '14_1.png', 'istype-wikidata-image' ),
				self::makeRow( '14_2.png', 'istype-commons-category' ),
			] ] ],
			self::SUGGESTIONS_URL . '15' => [ 200, [ 'rows' => [
				self::makeRow( '15.png', 'istype-lead-image', [ 'enwiki', 'dewiki' ] ),
			] ] ],
		] );
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			StatsFactory::newNull(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class ),
			new NullLogger()
		);

		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '11' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '12' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '13' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );

		$provider->setMaxSuggestionsToProcess( 2 );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '14' ), $taskType );
		$this->assertInstanceOf( ImageRecommendation::class, $recommendation );
		$this->assertSame( '14', $recommendation->getTitle()->getText() );
		$this->assertCount( 2, $recommendation->getImages() );
		$this->assertSame( '14_1.png', $recommendation->getImages()[0]->getImageTitle()->getDBkey() );
		$this->assertSame( '14_2.png', $recommendation->getImages()[1]->getImageTitle()->getDBkey() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIDATA, $recommendation->getImages()[0]->getSource() );
		$this->assertSame( ImageRecommendationImage::SOURCE_COMMONS, $recommendation->getImages()[1]->getSource() );
		$this->assertSame( [], $recommendation->getImages()[0]->getProjects() );
		$this->assertSame( self::DATASET_ID, $recommendation->getDatasetId() );

		$provider->setMaxSuggestionsToProcess( 1 );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '14' ), $taskType );
		$this->assertCount( 1, $recommendation->getImages() );

		$recommendation = $provider->get( new TitleValue( NS_MAIN, '15' ), $taskType );
		$this->assertInstanceOf( ImageRecommendation::class, $recommendation );
		$this->assertCount( 1, $recommendation->getImages() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIPEDIA, $recommendation->getImages()[0]->getSource() );
		$this->assertSame( [ 'enwiki', 'dewiki' ], $recommendation->getImages()[0]->getProjects() );
	}

	public static function provideTaskTypes(): iterable {
		return [
			'image suggestions' => [
				new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_MEDIUM ),
			],
			'section-level image suggestions' => [
				new SectionImageRecommendationTaskType( 'section-image-recommendation', TaskType::DIFFICULTY_MEDIUM ),
			],
		];
	}

	/**
	 * @dataProvider provideTaskTypes
	 * @covers ::get
	 */
	public function testGet_instrumentation( TaskType $taskType ) {
		$titleFactory = $this->getTitleFactory();
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$metadataProvider->method( 'getMetadata' )->willReturn( self::metadataFactory() );
		$apiHandler = $this->getApiHandler( [
			self::SUGGESTIONS_URL . '15' => [ 200, [ 'rows' => [
				self::makeRow( '15.png', 'istype-lead-image', [ 'enwiki', 'dewiki' ] ),
			] ] ],
		] );

		$unitTestingHelper = StatsFactory::newUnitTestingHelper();
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			$unitTestingHelper->getStatsFactory(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class ),
			new NullLogger()
		);

		$provider->get( new TitleValue( NS_MAIN, '15' ), $taskType );

		$taskTypeFilter = 'task_type=' . StatsUtils::normalizeString( $taskType->getId() );
		$requestSelector = "GrowthExperiments.image_recommendation_provider_seconds{action=get,$taskTypeFilter}";
		$this->assertSame(
			1,
			$unitTestingHelper->count( $requestSelector )
		);
		$this->assertIsFloat(
			$unitTestingHelper->last( $requestSelector )
		);

		$processingSelector = 'GrowthExperiments.image_recommendation_provider_seconds{'
		 . 'action=process_api_response_data,'
		 . $taskTypeFilter . '}';
		$this->assertSame(
			1,
			$unitTestingHelper->count( $processingSelector )
		);
		$this->assertIsFloat(
			$unitTestingHelper->last( $processingSelector )
		);
	}

	/**
	 * @covers ::get
	 */
	public function testGet_UseTitles() {
		$titleFactory = $this->getTitleFactory();
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$metadataProvider->method( 'getMetadata' )->willReturn( self::metadataFactory() );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$apiHandler = $this->getApiHandler( [
			'http://example.com/private/image_suggestions/title_cache/enwiki/10' => [ 200,
				[ 'rows' => [ [ 'page_id' => 1234 ] ] ] ],
			self::SUGGESTIONS_URL . '1234' => [ 200, [ 'rows' => [
				self::makeRow( '1234.png', 'istype-lead-image' ),
			] ] ],
		], true );
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			StatsFactory::newNull(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class ),
			new NullLogger()
		);
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertInstanceOf( ImageRecommendation::class, $recommendation );
		$this->assertSame( '1234.png', $recommendation->getImages()[0]->getImageTitle()->getDBkey() );
	}

	/**
	 * @covers ::processApiResponseData
	 */
	public function testMetadataError() {
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn(
			StatusValue::newFatal( 'rawmessage', 'No metadata' )
		);
		$apiHandler = $this->getApiHandler( [
			self::SUGGESTIONS_URL . '10' => [ 200, [ 'rows' => [
				self::makeRow( '1.png', 'istype-wikidata-image' ),
				self::makeRow( '2.png', 'istype-commons-category' ),
			] ] ],
		] );
		$provider = new ServiceImageRecommendationProvider(
			$this->getTitleFactory(),
			StatsFactory::newNull(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class ),
			new NullLogger()
		);
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertTrue( $recommendation instanceof StatusValue );
	}

	/**
	 * @covers ::processApiResponseData
	 */
	public function testPartialMetadataError() {
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$metadataProvider->method( 'getMetadata' )->willReturnCallback( static function ( $suggestion ) {
			if ( $suggestion['filename'] === 'Bad.png' ) {
				return StatusValue::newFatal( 'rawmessage', 'No metadata' );
			} else {
				return self::metadataFactory();
			}
		} );
		$apiHandler = $this->getApiHandler( [
			self::SUGGESTIONS_URL . '10' => [ 200, [ 'rows' => [
				self::makeRow( 'Bad.png', 'istype-wikidata-image' ),
				self::makeRow( 'Good.png', 'istype-commons-category' ),
			] ] ],
		] );
		$provider = new ServiceImageRecommendationProvider(
			$this->getTitleFactory(),
			StatsFactory::newNull(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class ),
			new NullLogger()
		);
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertTrue( $recommendation instanceof ImageRecommendation );
		$this->assertCount( 1, $recommendation->getImages() );
		$this->assertSame( 'Good.png', $recommendation->getImages()[0]->getImageTitle()->getDBkey() );
	}

	/**
	 * @covers ::processApiResponseData
	 */
	public function testAllSuggestionsFiltered() {
		$mockMetadataProvider = $this->createNoOpMock(
			ImageRecommendationMetadataProvider::class, [ 'getMetadata', 'getFileMetadata' ] );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$mockMetadataProvider->method( 'getFileMetadata' )
			->willReturn( self::metadataFactory( 200, MEDIATYPE_AUDIO ) );
		$mockMetadataProvider->method( 'getMetadata' )
			->willReturn( self::metadataFactory( 200, MEDIATYPE_AUDIO ) );
		$result = ServiceImageRecommendationProvider::processApiResponseData(
			$taskType,
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Foo' ),
			'Foo',
			[
				new ImageRecommendationData( 'Bad.png', 'wikidata', '', 'x' ),
			],
			$mockMetadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);

		$this->assertInstanceOf( StatusValue::class, $result );
		$this->assertStatusOK( $result );
		$this->assertSame(
			'Invalid file Bad.png in article Foo. Filtered because AUDIO is not valid mime type (BITMAP, DRAWING)',
			$this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() )
				->getWikiText( $result, [ 'lang' => 'en' ] )
		);

		$mockMetadataProvider = $this->createNoOpMock(
			ImageRecommendationMetadataProvider::class, [ 'getMetadata', 'getFileMetadata' ] );
		$mockMetadataProvider->method( 'getFileMetadata' )
			->willReturn( self::metadataFactory( 99 ) );
		$mockMetadataProvider->method( 'getMetadata' )
			->willReturn( self::metadataFactory( 99 ) );
		$result = ServiceImageRecommendationProvider::processApiResponseData(
			$taskType,
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Foo' ),
			'Foo',
			[
				new ImageRecommendationData( 'Bad.png', 'wikidata', '', 'x' ),
			],
			$mockMetadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);

		$this->assertInstanceOf( StatusValue::class, $result );
		$this->assertStatusOK( $result );
		$this->assertSame(
			'Invalid file Bad.png in article Foo. Filtered because not wide enough: 99 (minimum 100)',
			$this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() )
				->getWikiText( $result, [ 'lang' => 'en' ] )
		);
	}

	/**
	 * @dataProvider provideProcessApiResponseData
	 * @param ImageRecommendationData[] $data Suggestion data from the API handler.
	 * @param array|null $expectedResult Expected result of ImageRecommendation::toArray, or null
	 *   on error.
	 * @covers ::processApiResponseData
	 */
	public function testProcessApiResponseData( array $data, ?array $expectedResult ) {
		$mockMetadataProvider = $this->createNoOpMock(
			ImageRecommendationMetadataProvider::class, [ 'getMetadata', 'getFileMetadata' ] );
		$mockMetadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$mockMetadataProvider->method( 'getMetadata' )->willReturn( self::metadataFactory() );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$result = ServiceImageRecommendationProvider::processApiResponseData(
			$taskType,
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Foo' ),
			'Foo',
			$data,
			$mockMetadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);
		if ( $expectedResult !== null ) {
			$this->assertInstanceOf( ImageRecommendation::class, $result );
			$this->assertSame( $expectedResult, $result->toArray() );
		} else {
			$this->assertInstanceOf( StatusValue::class, $result );
		}
	}

	/**
	 * @dataProvider provideProcessApiResponseDataFilter
	 * @param ImageRecommendationData[] $data Suggestion data from the API handler.
	 * @param array|null $expectedResult Expected result of ImageRecommendation::toArray, or null
	 *   on error.
	 * @covers ::processApiResponseData
	 */
	public function testProcessApiResponseDataFiltering( array $data, ?array $expectedResult ) {
		$metadataProvider = $this->createNoOpMock(
			ImageRecommendationMetadataProvider::class, [ 'getMetadata', 'getFileMetadata' ] );
		$getFileMetadata = function ( string $filename ) {
			switch ( $filename ) {
				case 'NoWidthInformed.png':
					return self::metadataFactory( false );
				case 'TooNarrow.png':
					return self::metadataFactory( 99 );
				case 'InvalidMediaType.png':
					return self::metadataFactory( 200, MEDIATYPE_AUDIO );
				default:
					return self::metadataFactory( 101 );
			}
		};
		$metadataProvider->method( 'getFileMetadata' )
			->willReturnCallback( $getFileMetadata );
		$metadataProvider->method( 'getMetadata' )
			->willReturnCallback( static fn ( $suggestion ) => $getFileMetadata( $suggestion['filename'] ) );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$result = ServiceImageRecommendationProvider::processApiResponseData(
			$taskType,
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Foo' ),
			'Foo',
			$data,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);

		$this->assertInstanceOf( ImageRecommendation::class, $result );
		$this->assertSame( $expectedResult, $result->toArray() );
	}

	/**
	 * @dataProvider provideProcessApiResponseDataNoSuggestions
	 * @param ImageRecommendationData[] $data Suggestion data from the API handler.
	 * @covers ::processApiResponseData
	 */
	public function testProvideProcessApiResponseDataNoSuggestions( array $data ) {
		$metadataProvider = $this->createNoOpMock(
			ImageRecommendationMetadataProvider::class, [ 'getFileMetadata' ] );
		$metadataProvider->method( 'getFileMetadata' )->willReturnCallback( function ( $filename ) {
			switch ( $filename ) {
				case 'NoWidthInformed.png':
					return self::metadataFactory( false );
				default:
					return self::metadataFactory( 99 );
			}
		} );
		$submissionHandler = $this->createMock( AddImageSubmissionHandler::class );
		$context = $this;
		$titleText = 'Foo';
		$submissionHandler->method( 'invalidateRecommendation' )->willReturnCallback(
			static function ( $_, ProperPageIdentity $page ) use ( $context, $titleText ) {
				$context->assertSame( $page->getDBkey(), $titleText );
			}
		);
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$result = ServiceImageRecommendationProvider::processApiResponseData(
			$taskType,
			PageIdentityValue::localIdentity( 0, NS_MAIN, $titleText ),
			$titleText,
			$data,
			$metadataProvider,
			$submissionHandler
		);
		$this->assertInstanceOf( StatusValue::class, $result );
	}

	public static function metadataFactory( $width = 1024, $mediaType = MEDIATYPE_BITMAP ): array {
		return [
			'description' => 'description',
			'thumbUrl' => 'thumb url',
			'fullUrl' => 'full url',
			'descriptionUrl' => 'description url',
			'originalWidth' => $width,
			'originalHeight' => 768,
			'mediaType' => $mediaType,
		];
	}

	public static function provideProcessApiResponseDataFilter(): array {
		$validSuggestions = array_map(
			static fn ( string $filename ) => new ImageRecommendationData(
				$filename, 'wikipedia', 'enwiki,dewiki', '1.23'
			),
			[ 'TooNarrow.png', 'WideEnough.png', 'NoWidthInformed.png', 'InvalidMediaType.png' ]
		);

		return [
			'valid' => [ $validSuggestions, [
				'titleNamespace' => 0,
				'titleText' => 'Foo',
				'images' => [
					[
						'image' => 'WideEnough.png',
						'displayFilename' => 'WideEnough.png',
						'source' => 'wikipedia',
						'projects' => [ 'enwiki', 'dewiki' ],
						'metadata' => [
							'description' => 'description',
							'thumbUrl' => 'thumb url',
							'fullUrl' => 'full url',
							'descriptionUrl' => 'description url',
							'originalWidth' => 101,
							'originalHeight' => 768,
							'mediaType' => 'BITMAP',
						],
						'sectionNumber' => null,
						'sectionTitle' => null,
					],
				],
				'datasetId' => '1.23',
			] ],
		];
	}

	public static function provideProcessApiResponseData(): array {
		$validSuggestion = new ImageRecommendationData( 'Foo.png', 'wikipedia', 'enwiki,dewiki', '1.23' );
		$secondValidSuggestion = new ImageRecommendationData( 'bar.png', 'wikipedia', 'enwiki,dewiki', '1.23' );

		return [
			'no suggestions' => [ [], null ],
			'invalid filename' => [ [
				new ImageRecommendationData( [], 'wikipedia', 'enwiki,dewiki', '1.23' ),
			], null ],
			'invalid filename #2' => [ [
				new ImageRecommendationData( 'Foo<script>.png', 'wikipedia', 'enwiki,dewiki', '1.23' ),
			], null ],
			'invalid source' => [ [
				new ImageRecommendationData( 'Foo.png', true, 'enwiki,dewiki', '1.23' ),
			], null ],
			'invalid source #2' => [ [
				new ImageRecommendationData( 'Foo.png', 'foo', 'enwiki,dewiki', '1.23' ),
			], null ],
			'invalid projects' => [ [
				new ImageRecommendationData( 'Foo.png', 'wikipedia', true, '1.23' ),
			], null ],
			'invalid dataset ID' => [ [
				new ImageRecommendationData( 'Foo.png', 'wikipedia', 'enwiki,dewiki', true ),
			], null ],
			'valid' => [ [ $validSuggestion, $secondValidSuggestion ], [
				'titleNamespace' => 0,
				'titleText' => 'Foo',
				'images' => [
					[
						'image' => 'Foo.png',
						'displayFilename' => 'Foo.png',
						'source' => 'wikipedia',
						'projects' => [ 'enwiki', 'dewiki' ],
						'metadata' => [
							'description' => 'description',
							'thumbUrl' => 'thumb url',
							'fullUrl' => 'full url',
							'descriptionUrl' => 'description url',
							'originalWidth' => 1024,
							'originalHeight' => 768,
							'mediaType' => 'BITMAP',
						],
						'sectionNumber' => null,
						'sectionTitle' => null,
					],
				],
				'datasetId' => '1.23',
			] ],
		];
	}

	public static function provideProcessApiResponseDataNoSuggestions(): array {
		return [
			'all filtered' => [ [
				new ImageRecommendationData( 'TooNarrow.png', 'wikipedia', 'enwiki,dewiki', '1.23' ),
				new ImageRecommendationData( 'NoWidthInformed.png', 'wikipedia', 'enwiki,dewiki', '1.23' ),
			] ],
			'no suggestions' => [ [] ],
		];
	}

	/**
	 * @return TitleFactory|MockObject
	 */
	private function getTitleFactory(): TitleFactory {
		$titleFactory = $this->createNoOpMock( TitleFactory::class, [ 'newFromLinkTarget' ] );
		$titleFactory->method( 'newFromLinkTarget' )->willReturnCallback(
			function ( LinkTarget $title ) {
				// We need guessable IDs, so pass them as page name.
				return $this->makeMockTitle( $title->getText(), [ 'id' => (int)$title->getText() ] );
			} );
		return $titleFactory;
	}

	/**
	 * @param array[] $responseMap URL => [ status code, response body ]
	 * @return HttpRequestFactory|MockObject
	 */
	private function getHttpRequestFactory( array $responseMap ): HttpRequestFactory {
		$factory = $this->createNoOpMock( HttpRequestFactory::class, [ 'create' ] );
		$factory->method( 'create' )
			->willReturnCallback( function ( $url, $opts, $method ) use ( $responseMap ) {
				$response = $responseMap[$url] ?? null;
				if ( !$response ) {
					$this->fail( 'URL not configured: ' . $url );
				}
				[ $statusCode, $responseBody ] = $response;
				if ( !is_string( $responseBody ) ) {
					$responseBody = json_encode( $responseBody );
				}

				$request = $this->createNoOpMock( MWHttpRequest::class,
					[ 'setHeader', 'execute', 'getContent', 'getStatus' ] );
				$request->method( 'execute' )->willReturn(
					( $statusCode === 200 )
						? StatusValue::newGood()
						: StatusValue::newFatal( 'http' )
				);
				$request->method( 'getContent' )->willReturn( $responseBody );
				$request->method( 'getStatus' )->willReturn( $statusCode );

				return $request;
			} );
		return $factory;
	}

	/**
	 * @param array[] $responseMap URL => [ status code, response body ]
	 * @param bool $useTitles
	 * @return ProductionImageRecommendationApiHandler
	 */
	private function getApiHandler(
		array $responseMap,
		bool $useTitles = false
	): ProductionImageRecommendationApiHandler {
		return new ProductionImageRecommendationApiHandler(
			$this->getHttpRequestFactory( $responseMap ),
			'http://example.com',
			'enwiki',
			$this->getServiceContainer()->getGlobalIdGenerator(),
			null,
			$useTitles
		);
	}

	/**
	 * Make one row of an image suggestions service response.
	 */
	private static function makeRow(
		string $image,
		string $kind,
		array $foundOn = [],
		?string $sectionHeading = null,
		?int $sectionIndex = null
	): array {
		return [
			'wiki' => 'enwiki',
			'page_id' => 1,
			'id' => self::DATASET_ID,
			'image' => $image,
			'confidence' => 80,
			'found_on' => $foundOn,
			'kind' => [ $kind ],
			'origin_wiki' => 'commonswiki',
			'page_rev' => 1,
			'section_heading' => $sectionHeading,
			'section_index' => $sectionIndex,
		];
	}
}
