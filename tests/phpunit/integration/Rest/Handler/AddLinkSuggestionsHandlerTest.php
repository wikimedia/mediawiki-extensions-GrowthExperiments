<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\ErrorException;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\Rest\Handler\AddLinkSuggestionsHandler;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\ResponseFactory;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RawMessage;
use StatusValue;
use TitleValue;
use Wikimedia\TestingAccessWrapper;

class AddLinkSuggestionsHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\Rest\Handler\AddLinkSuggestionsHandler::run
	 */
	public function testRun() {
		$this->setMwGlobals( 'wgGENewcomerTasksLinkRecommendationsEnabled', true );
		$goodTitle = new TitleValue( NS_USER, 'Foo' );
		$badTitle = new TitleValue( NS_USER, 'Bar' );
		$linkData = [ [
			'link_text' => 'foo',
			'link_target' => 'Bar',
			'match_index' => 1,
			'wikitext_offset' => 100,
			'score' => 0.9,
			'context_before' => 'b',
			'context_after' => 'a',
			'link_index' => 2,
		] ];
		$links = LinkRecommendation::getLinksFromArray( $linkData );
		$linkRecommendationHelper = $this->getMockLinkRecommendationHelper( [
			$this->getTitleKey( $goodTitle ) => new LinkRecommendation( $goodTitle, 1, 1, $links ),
			$this->getTitleKey( $badTitle ) => StatusValue::newFatal( new RawMessage( 'error' ) ),
		] );
		$handler = new AddLinkSuggestionsHandler( $linkRecommendationHelper );
		$this->setResponseFactory( $handler );

		$this->assertSame( [ 'recommendation' => [ 'links' => $linkData ] ], $handler->run( $goodTitle ) );

		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( 'error' );
		$handler->run( $badTitle );
	}

	/**
	 * @param (LinkRecommendation|StatusValue) $recommendationMap Title to recommendation.
	 * @return LinkRecommendationHelper|MockObject
	 */
	private function getMockLinkRecommendationHelper( $recommendationMap ) {
		$mock = $this->createMock( LinkRecommendationHelper::class );
		$mock->method( 'getLinkRecommendation' )->willReturnCallback(
			function ( LinkTarget $linkTarget ) use ( $recommendationMap ) {
				$key = $this->getTitleKey( $linkTarget );
				$this->assertArrayHasKey( $key, $recommendationMap, 'page not configured: ' . $key );
				if ( $recommendationMap[$key] instanceof StatusValue ) {
					throw new ErrorException( $recommendationMap[$key] );
				}
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

	private function getTitleKey( LinkTarget $linkTarget ): string {
		return $linkTarget->getNamespace() . ':' . $linkTarget->getDBkey();
	}

}
