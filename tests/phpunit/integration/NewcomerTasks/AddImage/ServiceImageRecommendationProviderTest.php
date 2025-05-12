<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationImage;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\MvpImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use Wikimedia\Stats\Metrics\TimingMetric;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Stats\StatsUtils;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider
 */
class ServiceImageRecommendationProviderTest extends MediaWikiIntegrationTestCase {
	// These should really be unit tests but must be declared as integration tests because
	// File::normalizeTitle() cannot be mocked and has all kinds of dependencies :(

	use MockTitleTrait;

	/**
	 * @covers ::get
	 * @covers ::processApiResponseData
	 */
	public function testGet() {
		$titleFactory = $this->getTitleFactory();
		$url = 'http://example.com';
		$wikiProject = 'wikipedia';
		$wikiLanguage = 'en';
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$metadataProvider->method( 'getMetadata' )->willReturn( self::metadataFactory() );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$useTitle = true;
		$apiHandler = new MvpImageRecommendationApiHandler(
			$this->getHttpRequestFactory( [
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/10?source=ima' => [ 400, [] ],
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/11?source=ima' => [ 200, '{{{' ],
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/12?source=ima' => [ 200,
					[ 'pages' => [] ] ],
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/13?source=ima' => [ 200,
					[ 'pages' => [ [ 'suggestions' => [] ] ] ] ],
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/14?source=ima' => [ 200,
					[ 'pages' => [ [ 'suggestions' => [
						[ 'filename' => '14_1.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'wikidata', 'found_on' => '', 'dataset_id' => 'x',
						] ] ],
						[ 'filename' => '14_2.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'commons', 'found_on' => '', 'dataset_id' => 'x',
						] ] ],
					] ] ] ] ],
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/15?source=ima' => [ 200,
					[ 'pages' => [ [ 'suggestions' => [
						[ 'filename' => '15.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'wikipedia', 'found_on' => 'enwiki,dewiki', 'dataset_id' => 'x',
						] ] ],
					] ] ] ] ],
			] ),
			$url,
			$wikiProject,
			$wikiLanguage,
			null,
			null,
			$useTitle
		);
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			$this->getStatsFactory(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
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
		$this->assertSame( 'x', $recommendation->getDatasetId() );

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
				new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_MEDIUM )
			],
			'section-level image suggestions' => [
				new SectionImageRecommendationTaskType( 'section-image-recommendation', TaskType::DIFFICULTY_MEDIUM )
			],
		];
	}

	/**
	 * @dataProvider provideTaskTypes
	 * @covers ::get
	 */
	public function testGet_instrumentation( TaskType $taskType ) {
		$titleFactory = $this->getTitleFactory();
		$url = 'http://example.com';
		$wikiProject = 'wikipedia';
		$wikiLanguage = 'en';
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$metadataProvider->method( 'getMetadata' )->willReturn( self::metadataFactory() );
		$useTitle = true;
		$apiHandler = new MvpImageRecommendationApiHandler(
			$this->getHttpRequestFactory( [
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/15?source=ima' => [ 200,
					[ 'pages' => [ [ 'suggestions' => [
						[ 'filename' => '15.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'wikipedia', 'found_on' => 'enwiki,dewiki', 'dataset_id' => 'x',
						] ] ],
					] ] ] ] ],
			] ),
			$url,
			$wikiProject,
			$wikiLanguage,
			null,
			null,
			$useTitle
		);

		$unitTestingHelper = StatsFactory::newUnitTestingHelper();
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			$unitTestingHelper->getStatsFactory(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
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
	public function testGet_Id() {
		$titleFactory = $this->getTitleFactory();
		$url = 'http://example.com';
		$wikiProject = 'wikipedia';
		$wikiLanguage = 'en';
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn( self::metadataFactory() );
		$metadataProvider->method( 'getMetadata' )->willReturn( self::metadataFactory() );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$useTitle = false;
		$apiHandler = new MvpImageRecommendationApiHandler(
			$this->getHttpRequestFactory( [
				'http://example.com/image-suggestions/v0/wikipedia/en/pages?source=ima&id=10' => [ 200,
					[ 'pages' => [] ] ],
			] ),
			$url,
			$wikiProject,
			$wikiLanguage,
			null,
			null,
			$useTitle
		);
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			$this->getStatsFactory(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
	}

	/**
	 * @covers ::processApiResponseData
	 */
	public function testMetadataError() {
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$url = 'http://example.com';
		$wikiProject = 'wikipedia';
		$wikiLanguage = 'en';
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$metadataProvider->method( 'getFileMetadata' )->willReturn(
			StatusValue::newFatal( 'rawmessage', 'No metadata' )
		);
		$useTitle = true;
		$apiHandler = new MvpImageRecommendationApiHandler(
			$this->getHttpRequestFactory( [
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/10?source=ima' => [ 200,
					[ 'pages' => [ [ 'suggestions' => [
						[ 'filename' => '1.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'wikidata', 'found_on' => '', 'dataset_id' => 'x',
						] ] ],
						[ 'filename' => '2.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'commons', 'found_on' => '', 'dataset_id' => 'x',
						] ] ],
					] ] ] ] ],
			] ),
			$url,
			$wikiProject,
			$wikiLanguage,
			null,
			null,
			$useTitle
		);
		$provider = new ServiceImageRecommendationProvider(
			$this->getTitleFactory(),
			$this->getStatsFactory(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertTrue( $recommendation instanceof StatusValue );
	}

	/**
	 * @covers ::processApiResponseData
	 */
	public function testPartialMetadataError() {
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$url = 'http://example.com';
		$wikiProject = 'wikipedia';
		$wikiLanguage = 'en';
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
		$useTitle = true;
		$apiHandler = new MvpImageRecommendationApiHandler(
			$this->getHttpRequestFactory( [
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/10?source=ima' => [ 200,
					[ 'pages' => [ [ 'suggestions' => [
						[ 'filename' => 'Bad.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'wikidata', 'found_on' => '', 'dataset_id' => 'x',
						] ] ],
						[ 'filename' => 'Good.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'commons', 'found_on' => '', 'dataset_id' => 'x',
						] ] ],
					] ] ] ] ],
			] ),
			$url,
			$wikiProject,
			$wikiLanguage,
			null,
			null,
			$useTitle
		);
		$provider = new ServiceImageRecommendationProvider(
			$this->getTitleFactory(),
			$this->getStatsFactory(),
			$apiHandler,
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
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
			new PageIdentityValue( 0, NS_MAIN, 'Foo', PageIdentity::LOCAL ),
			'Foo',
			[
				new ImageRecommendationData( 'Bad.png', 'wikidata', '', 'x' )
			],
			$mockMetadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);

		$this->assertInstanceOf( StatusValue::class, $result );
		$this->assertTrue( $result->isOK() );
		$this->assertSame(
			'Invalid file Bad.png in article Foo. Filtered because AUDIO is not valid mime type (BITMAP, DRAWING)',
			Status::wrap( $result )->getWikiText( false, false, 'en' )
		);

		$mockMetadataProvider = $this->createNoOpMock(
			ImageRecommendationMetadataProvider::class, [ 'getMetadata', 'getFileMetadata' ] );
		$mockMetadataProvider->method( 'getFileMetadata' )
			->willReturn( self::metadataFactory( 99 ) );
		$mockMetadataProvider->method( 'getMetadata' )
			->willReturn( self::metadataFactory( 99 ) );
		$result = ServiceImageRecommendationProvider::processApiResponseData(
			$taskType,
			new PageIdentityValue( 0, NS_MAIN, 'Foo', PageIdentity::LOCAL ),
			'Foo',
			[
				new ImageRecommendationData( 'Bad.png', 'wikidata', '', 'x' )
			],
			$mockMetadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);

		$this->assertInstanceOf( StatusValue::class, $result );
		$this->assertTrue( $result->isOK() );
		$this->assertSame(
			'Invalid file Bad.png in article Foo. Filtered because not wide enough: 99 (minimum 100)',
			Status::wrap( $result )->getWikiText( false, false, 'en' )
		);
	}

	/**
	 * @dataProvider provideProcessApiResponseData
	 * @param array $data API response data.
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
			new PageIdentityValue( 0, NS_MAIN, 'Foo', PageIdentity::LOCAL ),
			'Foo',
			$this->formatOldApiResponse( $data, $taskType ),
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
	 * @param array $data API response data.
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
			new PageIdentityValue( 0, NS_MAIN, 'Foo', PageIdentity::LOCAL ),
			'Foo',
			$this->formatOldApiResponse( $data, $taskType ),
			$metadataProvider,
			$this->createMock( AddImageSubmissionHandler::class )
		);

		$this->assertInstanceOf( ImageRecommendation::class, $result );
		$this->assertSame( $expectedResult, $result->toArray() );
	}

	/**
	 * @dataProvider provideProcessApiResponseDataNoSuggestions
	 * @param array $data API response data.
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
			new PageIdentityValue( 0, NS_MAIN, $titleText, PageIdentity::LOCAL ),
			$titleText,
			$this->formatOldApiResponse( $data, $taskType ),
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
			'mediaType' => $mediaType
		];
	}

	public static function provideProcessApiResponseDataFilter(): array {
		$validSource = [
			'from' => 'wikipedia',
			'found_on' => 'enwiki,dewiki',
			'dataset_id' => '1.23',
		];
		$validSuggestions = [
			[
				'filename' => 'TooNarrow.png',
				'source' => [ 'details' => $validSource ],
			],
			[
				'filename' => 'WideEnough.png',
				'source' => [ 'details' => $validSource ],
			],
			[
				'filename' => 'NoWidthInformed.png',
				'source' => [ 'details' => $validSource ],
			],
			[
				'filename' => 'InvalidMediaType.png',
				'source' => [ 'details' => $validSource ],
			]
		];

		return [
			'valid' => [ [ 'pages' => [ [ 'suggestions' => $validSuggestions ] ] ], [
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
		$validSource = [
			'from' => 'wikipedia',
			'found_on' => 'enwiki,dewiki',
			'dataset_id' => '1.23',
		];
		$validSuggestion = [
			'filename' => 'Foo.png',
			'source' => [ 'details' => $validSource ],
		];
		$secondValidSuggestion = [
			'filename' => 'bar.png',
			'source' => [ 'details' => $validSource ]
		];

		return [
			'no page' => [ [ 'pages' => [] ], null ],
			'no suggestions' => [ [ 'pages' => [
				[ 'suggestions' => [] ],
			] ], null ],
			'invalid filename' => [ [ 'pages' => [
				[ 'suggestions' => [
					[ 'filename' => [] ] + $validSuggestion,
				] ],
			] ], null ],
			'invalid filename #2' => [ [ 'pages' => [
				[ 'suggestions' => [
					[ 'filename' => 'Foo<script>.png' ] + $validSuggestion,
				] ],
			] ], null ],
			'invalid from' => [ [ 'pages' => [
				[ 'suggestions' => [
					[ 'source' => [ 'details' => [ 'from' => true ] + $validSource ] ] + $validSuggestion,
				] ],
			] ], null ],
			'invalid from #2' => [ [ 'pages' => [
				[ 'suggestions' => [
					[ 'source' => [ 'details' => [ 'from' => 'foo' ] + $validSource ] ] + $validSuggestion,
				] ],
			] ], null ],
			'invalid found_on' => [ [ 'pages' => [
				[ 'suggestions' => [
					[ 'source' => [ 'details' => [ 'found_on' => true ] + $validSource ] ] + $validSuggestion,
				] ],
			] ], null ],
			'invalid dataset_id' => [ [ 'pages' => [
				[ 'suggestions' => [
					[ 'source' => [ 'details' => [ 'dataset_id' => true ] + $validSource ] ] + $validSuggestion,
				] ],
			] ], null ],
			'valid' => [ [ 'pages' => [ [ 'suggestions' => [ $validSuggestion, $secondValidSuggestion ] ] ] ], [
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
		$validSource = [
			'from' => 'wikipedia',
			'found_on' => 'enwiki,dewiki',
			'dataset_id' => '1.23',
		];
		$validSuggestions = [
			[
				'filename' => 'TooNarrow.png',
				'source' => [ 'details' => $validSource ],
			],
			[
				'filename' => 'NoWidthInformed.png',
				'source' => [ 'details' => $validSource ],
			]
		];

		return [
			'all filtered' => [ [ 'pages' => [ [ 'suggestions' => $validSuggestions ] ] ] ],
			'no suggestions' => [ [ 'pages' => [ [ 'suggestions' => [] ] ] ] ],
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

	private function formatOldApiResponse( array $data, TaskType $taskType ): array {
		$apiHandler = new MvpImageRecommendationApiHandler(
			$this->createNoOpMock( HttpRequestFactory::class ),
			'https://example.com',
			'wikipedia',
			'en',
			null,
			null,
			false
		);
		return $apiHandler->getSuggestionDataFromApiResponse( $data, $taskType );
	}

	private function getStatsFactory() {
		$stats = $this->createMock( StatsFactory::class );
		$this->setService( 'StatsFactory', $stats );
		$stats->method( 'withComponent' )->willReturnSelf();

		$timing = $this->createMock( TimingMetric::class );
		$timing->method( 'setLabel' )->willReturnSelf();
		$timing->method( 'observeSeconds' )->willReturnSelf();
		$stats->method( 'getTiming' )->willReturn( $timing );

		return $stats;
	}
}
