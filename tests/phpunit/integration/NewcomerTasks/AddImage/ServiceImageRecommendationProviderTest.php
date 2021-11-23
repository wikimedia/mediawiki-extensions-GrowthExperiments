<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationImage;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use IBufferingStatsdDataFactory;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use TitleFactory;
use TitleValue;

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
		$metadataProvider->method( 'getMetadata' )->willReturn( [
			'description' => 'description',
			'thumbUrl' => 'thumb url',
			'fullUrl' => 'full url',
			'descriptionUrl' => 'description url',
			'originalWidth' => 1024,
			'originalHeight' => 768,
		] );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$useTitle = true;
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
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
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$url,
			null,
			$wikiProject,
			$wikiLanguage,
			$metadataProvider,
			null,
			$useTitle
		);

		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '11' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '12' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '13' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );

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

		$recommendation = $provider->get( new TitleValue( NS_MAIN, '15' ), $taskType );
		$this->assertInstanceOf( ImageRecommendation::class, $recommendation );
		$this->assertCount( 1, $recommendation->getImages() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIPEDIA, $recommendation->getImages()[0]->getSource() );
		$this->assertSame( [ 'enwiki', 'dewiki' ], $recommendation->getImages()[0]->getProjects() );
	}

	/**
	 * @covers ::get
	 */
	public function testGet_instrumentation() {
		$titleFactory = $this->getTitleFactory();
		$url = 'http://example.com';
		$wikiProject = 'wikipedia';
		$wikiLanguage = 'en';
		$metadataProvider = $this->createMock(
			ImageRecommendationMetadataProvider::class
		);
		$statsDataFactory = $this->createMock( IBufferingStatsdDataFactory::class );
		$metadataProvider->method( 'getMetadata' )->willReturn( [
			'description' => 'description',
			'thumbUrl' => 'thumb url',
			'fullUrl' => 'full url',
			'descriptionUrl' => 'description url',
			'originalWidth' => 1024,
			'originalHeight' => 768,
		] );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$useTitle = true;
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			$this->getHttpRequestFactory( [
				'http://example.com/image-suggestions/v0/wikipedia/en/pages/15?source=ima' => [ 200,
					[ 'pages' => [ [ 'suggestions' => [
						[ 'filename' => '15.png', 'source' => [ 'name' => 'ima', 'details' => [
							'from' => 'wikipedia', 'found_on' => 'enwiki,dewiki', 'dataset_id' => 'x',
						] ] ],
					] ] ] ] ],
			] ),
			$statsDataFactory,
			$url,
			null,
			$wikiProject,
			$wikiLanguage,
			$metadataProvider,
			null,
			$useTitle
		);

		$statsDataFactory->expects( $this->exactly( 2 ) )
			->method( 'timing' )
			->withConsecutive(
				[ $this->equalTo(
					'timing.growthExperiments.imageRecommendationProvider.get'
				) ],
				[ $this->equalTo(
					'timing.growthExperiments.imageRecommendationProvider.processApiResponseData'
				) ]
			);

		$provider->get( new TitleValue( NS_MAIN, '15' ), $taskType );
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
		$metadataProvider->method( 'getMetadata' )->willReturn( [
			'description' => 'description',
			'thumbUrl' => 'thumb url',
			'fullUrl' => 'full url',
			'descriptionUrl' => 'description url',
			'originalWidth' => 1024,
			'originalHeight' => 768,
		] );
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$useTitle = false;
		$provider = new ServiceImageRecommendationProvider(
			$titleFactory,
			$this->getHttpRequestFactory( [
				'http://example.com/image-suggestions/v0/wikipedia/en/pages?source=ima&id=10' => [ 200,
				   [ 'pages' => [] ] ],
			] ),
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$url,
			null,
			$wikiProject,
			$wikiLanguage,
			$metadataProvider,
			null,
			$useTitle
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
		$metadataProvider->method( 'getMetadata' )->willReturn(
			StatusValue::newFatal( 'rawmessage', 'No metadata' )
		);
		$useTitle = true;
		$provider = new ServiceImageRecommendationProvider(
			$this->getTitleFactory(),
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
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$url,
			null,
			$wikiProject,
			$wikiLanguage,
			$metadataProvider,
			null,
			$useTitle
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
		$metadataProvider->method( 'getMetadata' )->willReturnCallback( static function ( $suggestion ) {
			if ( $suggestion['filename'] === 'Bad.png' ) {
				return StatusValue::newFatal( 'rawmessage', 'No metadata' );
			} else {
				return [
					'description' => 'description',
					'thumbUrl' => 'thumb url',
					'fullUrl' => 'full url',
					'descriptionUrl' => 'description url',
					'originalWidth' => 1024,
					'originalHeight' => 768,
				];
			}
		} );
		$useTitle = true;
		$provider = new ServiceImageRecommendationProvider(
			$this->getTitleFactory(),
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
			$this->createMock( IBufferingStatsdDataFactory::class ),
			$url,
			null,
			$wikiProject,
			$wikiLanguage,
			$metadataProvider,
			null,
			$useTitle
		);
		$recommendation = $provider->get( new TitleValue( NS_MAIN, '10' ), $taskType );
		$this->assertTrue( $recommendation instanceof ImageRecommendation );
		$this->assertCount( 1, $recommendation->getImages() );
		$this->assertSame( 'Good.png', $recommendation->getImages()[0]->getImageTitle()->getDBkey() );
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
			ImageRecommendationMetadataProvider::class, [ 'getMetadata' ] );
		$mockMetadataProvider->method( 'getMetadata' )->willReturn( [] );
		$result = ServiceImageRecommendationProvider::processApiResponseData(
			new TitleValue( 0, 'Foo' ),
			'Foo',
			$data,
			$mockMetadataProvider
		);
		if ( $expectedResult !== null ) {
			$this->assertInstanceOf( ImageRecommendation::class, $result );
			$this->assertSame( $expectedResult, $result->toArray() );
		} else {
			$this->assertInstanceOf( StatusValue::class, $result );
		}
	}

	public function provideProcessApiResponseData() {
		$validSource = [
			'from' => 'wikipedia',
			'found_on' => 'enwiki,dewiki',
			'dataset_id' => '1.23',
		];
		$validSuggestion = [
			'filename' => 'Foo.png',
			'source' => [ 'details' => $validSource ],
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
			'valid' => [ [ 'pages' => [ [ 'suggestions' => [ $validSuggestion ] ] ] ], [
				'titleNamespace' => 0,
				'titleText' => 'Foo',
				'images' => [
					[
						'image' => 'Foo.png',
						'displayFilename' => 'Foo.png',
						'source' => 'wikipedia',
						'projects' => [ 'enwiki', 'dewiki' ],
						'metadata' => [],
					],
				],
				'datasetId' => '1.23',
			] ],
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
				list( $statusCode, $responseBody ) = $response;
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

}
