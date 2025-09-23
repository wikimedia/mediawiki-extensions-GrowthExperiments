<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\ErrorException;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\Rest\Handler\AddLinkSuggestionsHandler;
use MediaWiki\Language\RawMessage;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\ResponseFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use Wikimedia\TestingAccessWrapper;

class AddLinkSuggestionsHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\Rest\Handler\AddLinkSuggestionsHandler::run
	 */
	public function testRun() {
		$this->overrideConfigValue( 'GENewcomerTasksLinkRecommendationsEnabled', true );
		$goodTitle = new TitleValue( NS_USER, 'Foo' );
		$badTitle = new TitleValue( NS_USER, 'Bar' );
		$currentTime = 1577865600;
		MWTimestamp::setFakeTime( $currentTime );
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
		$metadata = [
			'application_version' => 'f86340f',
			'dataset_checksums' => [
				'anchors' => '5a186f92365cecca979a38de3e133bbf6089984f35b95cacd93e19a0403e00ab',
				'model' => 'dd650648b87d69547b1721560f0e16027e5f81ccb5dd2dcfdcf121d460abb53c',
				'pageids' => '9e1b004e7fa84b0187a486c682a1cbf152ca52d2e769d9c3a140eefe9b540d44',
				'redirects' => '57c5ce51d0ce4048743674a7ebb53e7a527d325765fd0632fa1455be46f7eb3c',
				'w2vfiltered' => '096a5f2ca30708ce41408897c99efb854204ac8322fbada8a8147d8156b031e8',
			],
			'format_version' => 1,
		];
		$links = LinkRecommendation::getLinksFromArray( $linkData );
		$meta = LinkRecommendation::getMetadataFromArray( $metadata );
		$linkRecommendationHelper = $this->getMockLinkRecommendationHelper( [
			$this->getTitleKey( $goodTitle ) => new LinkRecommendation( $goodTitle, 1, 1, $links, $meta ),
			$this->getTitleKey( $badTitle ) => StatusValue::newFatal( new RawMessage( 'error' ) ),
		] );
		$handler = new AddLinkSuggestionsHandler( $linkRecommendationHelper );
		$this->setResponseFactory( $handler );

		$this->assertSame(
			[
				'recommendation' => [
					'links' => $linkData,
					'meta' => $metadata + [
						'task_timestamp' => LinkRecommendation::FALLBACK_TASK_TIMESTAMP,
					],
				],
			],
			$handler->run( $goodTitle )
		);

		$linkRecommendationHelperNoMetadata = $this->getMockLinkRecommendationHelper( [
			$this->getTitleKey( $goodTitle ) => new LinkRecommendation(
				$goodTitle,
				1,
				1,
				$links,
				LinkRecommendation::getMetadataFromArray( [] )
			),
			$this->getTitleKey( $badTitle ) => StatusValue::newFatal( new RawMessage( 'error' ) ),
		] );
		$handler = new AddLinkSuggestionsHandler( $linkRecommendationHelperNoMetadata );
		$this->setResponseFactory( $handler );

		$this->assertSame(
			[ 'recommendation' => [
				'links' => $linkData,
				'meta' => [
					'application_version' => '',
					'dataset_checksums' => [],
					'format_version' => 1,
					'task_timestamp' => LinkRecommendation::FALLBACK_TASK_TIMESTAMP,
				],
			] ],
			$handler->run( $goodTitle )
		);

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
			->willReturnCallback( static function ( $code, $error ) {
				return [ 'response' => $error ];
			} );
		TestingAccessWrapper::newFromObject( $handler )->responseFactory = $mockResponseFactory;
	}

	private function getTitleKey( LinkTarget $linkTarget ): string {
		return $linkTarget->getNamespace() . ':' . $linkTarget->getDBkey();
	}

}
