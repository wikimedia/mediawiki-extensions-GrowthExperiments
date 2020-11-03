<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\Rest\Handler\AddLinkSuggestionsHandler;
use MediaWiki\Rest\ResponseFactory;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RawMessage;
use StatusValue;
use TitleValue;
use Wikimedia\TestingAccessWrapper;

class AddLinkSuggestionsHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \GrowthExperiments\Rest\Handler\AddLinkSuggestionsHandler::run
	 */
	public function testRun() {
		$goodTitle = new TitleValue( NS_USER, 'Foo' );
		$badTitle = new TitleValue( NS_USER, 'Bar' );
		$linkData = [ 'links' => [ 'x' ] ];
		$linkRecommendationProvider = $this->getMockLinkRecommendationProvider( [
			$this->getTitleKey( $goodTitle ) => new LinkRecommendation( $goodTitle, 1, 1, $linkData ),
			$this->getTitleKey( $badTitle ) => StatusValue::newFatal( new RawMessage( 'error' ) ),
		] );
		$handler = new AddLinkSuggestionsHandler( $linkRecommendationProvider );
		$this->setResponseFactory( $handler );

		$this->assertSame( [ 'recommendation' => $linkData ], $handler->run( $goodTitle ) );

		$this->assertSame( [ 'response' => [ 'error' => 'error' ] ], $handler->run( $badTitle ) );
	}

	/**
	 * @param (LinkRecommendation|StatusValue) $recommendationMap Title to recommendation.
	 * @return LinkRecommendationProvider|MockObject
	 */
	private function getMockLinkRecommendationProvider( $recommendationMap ) {
		$mock = $this->createMock( LinkRecommendationProvider::class );
		$mock->method( 'get' )->willReturnCallback(
			function ( TitleValue $title ) use ( $recommendationMap ) {
				$key = $this->getTitleKey( $title );
				$this->assertArrayHasKey( $key, $recommendationMap, 'page not configured: ' . $key );
				return $recommendationMap[$key];
			} );
		return $mock;
	}

	private function setResponseFactory( AddLinkSuggestionsHandler $handler ) {
		$mockResponseFactory = $this->createMock( ResponseFactory::class );
		$mockResponseFactory->method( 'createHttpError' )
			->willReturnCallback( function ( $code, $error ) {
				return [ 'response' => $error ];
			} );
		TestingAccessWrapper::newFromObject( $handler )->responseFactory = $mockResponseFactory;
	}

	private function getTitleKey( TitleValue $title ): string {
		return $title->getNamespace() . ':' . $title->getDBkey();
	}

}
