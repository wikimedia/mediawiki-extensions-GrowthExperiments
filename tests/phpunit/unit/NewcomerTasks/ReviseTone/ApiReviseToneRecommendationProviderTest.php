<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ReviseTone\ApiReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ReviseTone\ReviseToneWeightedTagManager;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use Psr\Log\NullLogger;
use StatusValue;
use TitleValue;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ReviseTone\ApiReviseToneRecommendationProvider
 */
class ApiReviseToneRecommendationProviderTest extends MediaWikiUnitTestCase {

	public function testGet(): void {
		$titleMock = $this->createConfiguredMock( Title::class, [
			'getArticleID' => 123,
			'getLatestRevID' => 456,
		] );
		$titleFactoryMock = $this->createConfiguredMock( TitleFactory::class, [
			'newFromLinkTarget' => $titleMock,
		] );
		$apiResponse = [
			'rows' =>
				[
						[
							'content' => 'rain in spain',
							'idx' => -1,
							'model_version' => 'v1',
							'page_id' => 1,
							'score' => 0.7,
							'revision_id' => 10,
							'wiki_id' => 'enwiki',
						],
						[
							'content' => 'falls mostly on the plains',
							'idx' => -1,
							'model_version' => 'v1',
							'page_id' => 1,
							'score' => 0.3,
							'revision_id' => 10,
							'wiki_id' => 'enwiki',
						],
						[
							'content' => 'rain in spain',
							'idx' => -1,
							'model_version' => 'v2',
							'page_id' => 1,
							'score' => 0.5,
							'revision_id' => 10,
							'wiki_id' => 'enwiki',
						],
						[
							'content' => 'falls mostly on the plains',
							'idx' => -1,
							'model_version' => 'v2',
							'page_id' => 1,
							'score' => 0.6,
							'revision_id' => 10,
							'wiki_id' => 'enwiki',
						],
				],
		];
		$httpRequestFactoryMock = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactoryMock->expects( $this->once() )->method( 'create' )->with(
			'https://example.com/public/ml_cache/page_paragraph_tone_scores/testwiki/123/456',
		)->willReturn( $this->createConfiguredMock( MWHttpRequest::class, [
			'execute' => StatusValue::newGood(),
			'getContent' => json_encode( $apiResponse ),
		] ) );
		$reviseToneWeightedTagManagerMock = $this->createMock( ReviseToneWeightedTagManager::class );
		$sut = new ApiReviseToneRecommendationProvider(
			'https://example.com',
			'testwiki',
			$titleFactoryMock,
			$httpRequestFactoryMock,
			$reviseToneWeightedTagManagerMock,
			new NullLogger(),
			StatsFactory::newUnitTestingHelper()->getStatsFactory(),
		);

		$actualRecommendation = $sut->get(
			TitleValue::tryNew( 0, 'ExamplePage' ),
			new ReviseToneTaskType( ReviseToneTaskTypeHandler::TASK_TYPE_ID, 'easy' )
		);

		$this->assertSame(
			[
				'title' => 'ExamplePage',
				'paragraphText' => 'falls mostly on the plains',
			],
			$actualRecommendation->toArray()
		);
	}
}
