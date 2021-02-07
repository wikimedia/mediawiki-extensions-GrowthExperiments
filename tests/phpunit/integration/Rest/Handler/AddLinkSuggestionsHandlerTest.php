<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\Rest\Handler\AddLinkSuggestionsHandler;
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
		$configurationLoader = new StaticConfigurationLoader( [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => new LinkRecommendationTaskType(
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID, TaskType::DIFFICULTY_EASY, [] ),
		] );
		$linkRecommendationProvider = $this->getMockLinkRecommendationProvider( [
			$this->getTitleKey( $goodTitle ) => new LinkRecommendation( $goodTitle, 1, 1, $links ),
			$this->getTitleKey( $badTitle ) => StatusValue::newFatal( new RawMessage( 'error' ) ),
		] );
		$handler = new AddLinkSuggestionsHandler( $configurationLoader, $linkRecommendationProvider );
		$this->setResponseFactory( $handler );

		$this->assertSame( [ 'recommendation' => [ 'links' => $linkData ] ], $handler->run( $goodTitle ) );

		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( 'error' );
		$handler->run( $badTitle );
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
