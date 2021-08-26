<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use TitleFactory;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider
 */
class ServiceImageRecommendationProviderTest extends MediaWikiUnitTestCase {

	use MockTitleTrait;

	public function testGet() {
		/** @var TitleFactory|MockObject $titleFactory */
		$titleFactory = $this->createNoOpMock( TitleFactory::class, [ 'newFromLinkTarget' ] );
		$titleFactory->method( 'newFromLinkTarget' )->willReturnCallback(
			function ( LinkTarget $title ) {
				// We need guessable IDs, so pass them as page name.
				return $this->makeMockTitle( $title->getText(), [ 'id' => (int)$title->getText() ] );
			} );
		$url = 'http://example.com';
		$wikiProject = 'wikipedia';
		$wikiLanguage = 'en';
		$taskType = new ImageRecommendationTaskType( 'image-recommendation', TaskType::DIFFICULTY_EASY );
		$provider = new ServiceImageRecommendationProvider( $titleFactory, $this->getHttpRequestFactory( [
			'http://example.com/image-suggestions/v0/wikipedia/en/pages?id=10&source=ima' => [ 400, [] ],
			'http://example.com/image-suggestions/v0/wikipedia/en/pages?id=11&source=ima' => [ 200, '{{{' ],
			'http://example.com/image-suggestions/v0/wikipedia/en/pages?id=12&source=ima' => [ 200,
				[ 'pages' => [] ] ],
			'http://example.com/image-suggestions/v0/wikipedia/en/pages?id=13&source=ima' => [ 200,
				[ 'pages' => [ [ 'suggestions' => [] ] ] ] ],
			'http://example.com/image-suggestions/v0/wikipedia/en/pages?id=14&source=ima' => [ 200,
				[ 'pages' => [ [ 'suggestions' => [
					[ 'filename' => '14_1.png', 'source' => [ 'name' => 'ima', 'details' => [
						'from' => 'wikidata', 'found_on' => '', 'dataset_id' => 'x',
					] ] ],
				   [ 'filename' => '14_2.png', 'source' => [ 'name' => 'ima', 'details' => [
					   'from' => 'commons', 'found_on' => '', 'dataset_id' => 'x',
				   ] ] ],
				] ] ] ] ],
			'http://example.com/image-suggestions/v0/wikipedia/en/pages?id=15&source=ima' => [ 200,
				[ 'pages' => [ [ 'suggestions' => [
					[ 'filename' => '15.png', 'source' => [ 'name' => 'ima', 'details' => [
						'from' => 'wikipedia', 'found_on' => 'enwiki,dewiki', 'dataset_id' => 'x',
					] ] ],
				] ] ] ] ],
		] ), $url, $wikiProject, $wikiLanguage, null );

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
