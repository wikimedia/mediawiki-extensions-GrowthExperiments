<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy
 * FIXME part of SearchStrategy is tested in RemoteSearchTaskSuggesterTest
 */
class SearchStrategyTest extends MediaWikiUnitTestCase {

	public function testGetQueries() {
		$taskType = new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY,
			[], [ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ], [ new TitleValue( NS_TEMPLATE, 'DontCopyedit' ) ] );
		$oresTopic1 = new OresBasedTopic( 'art', 'culture', [ 'painting', 'drawing' ] );
		$oresTopic2 = new OresBasedTopic( 'science', 'stem', [ 'physics', 'biology' ] );
		$campaignTopic1 = new CampaignTopic( 'biology', 'hastemplate:Taxobox' );
		$campaignTopic2 = new CampaignTopic( 'argentina', 'hastemplate:Argentina' );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturn( 'hastemplate:"Copyedit" -hastemplate:"DontCopyedit"' );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$oresQueries = $searchStrategy->getQueries( [ $taskType ], [ $oresTopic1, $oresTopic2 ], [] );
		$this->assertCount( 1, $oresQueries );
		$this->assertTaskTypeInQueries( $oresQueries, [ 'copyedit' ] );
		$this->assertTopicsInMultiTopicQueries( $oresQueries, [ 'art', 'science' ] );
		$this->assertQueryStrings( $oresQueries, [
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" articletopic:painting|drawing|physics|biology',
		] );

		$restrictedQueries = $searchStrategy->getQueries( [ $taskType ],
			[ $oresTopic1, $oresTopic2 ], [ 1, 2, 3 ] );
		$this->assertCount( 1, $restrictedQueries );
		$this->assertTopicsInMultiTopicQueries( $restrictedQueries, [ 'art', 'science' ] );
		$this->assertQueryStrings( $restrictedQueries, [
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" articletopic:painting|drawing|physics|biology ' .
			'pageid:1|2|3',
		] );

		$searchExpressionBasedTopicQueries = $searchStrategy->getQueries( [ $taskType ],
			[ $campaignTopic1, $campaignTopic2 ] );
		$this->assertCount( 2, $searchExpressionBasedTopicQueries );
		$this->assertTaskTypeInQueries( $searchExpressionBasedTopicQueries, [ 'copyedit' ] );
		$this->assertTopicsInQueries( $searchExpressionBasedTopicQueries, [ 'biology', 'argentina' ] );
		$this->assertQueryStrings( $searchExpressionBasedTopicQueries, [
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" hastemplate:Taxobox',
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" hastemplate:Argentina',
		] );
	}

	public function testGetQueriesAll() {
		$taskType1 = new TaskType( 'link-recommendation', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'image-recommendation', TaskType::DIFFICULTY_MEDIUM );
		$oresTopic1 = new OresBasedTopic( 'literature', 'culture', [ 'literature', 'books' ] );
		$oresTopic2 = new OresBasedTopic( 'music', 'culture', [ 'music' ] );
		$campaignTopic = new CampaignTopic( 'argentina', 'growtharticle:argentina' );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturnOnConsecutiveCalls(
		'hasrecommendation:link',
				'hasrecommendation:image'
			);

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries(
			[ $taskType1, $taskType2 ],
			[ $campaignTopic, $oresTopic1, $oresTopic2 ],
			null,
			null,
			SearchStrategy::TOPIC_MATCH_MODE_AND
		);

		$this->assertCount( 2, $queries );
		$this->assertTaskTypeInQueries( $queries, [ 'link-recommendation', 'image-recommendation' ] );
		$this->assertIntersectionTopicsInQueries( $queries, [ 'literature', 'music', 'argentina' ] );
		$this->assertQueryStrings( $queries, [
			'hasrecommendation:image growtharticle:argentina articletopic:literature|books articletopic:music',
			'hasrecommendation:link growtharticle:argentina articletopic:literature|books articletopic:music',
		] );
	}

	public function testExclusion() {
		$excludedTemplates = [
			new TitleValue( NS_TEMPLATE, 'Foo' ),
			new TitleValue( NS_TEMPLATE, 'Bar' ),
		];
		$excludedCategories = [
			new TitleValue( NS_CATEGORY, 'Baz' ),
			new TitleValue( NS_CATEGORY, 'Boom' ),
		];
		$taskType = new TemplateBasedTaskType(
			'copyedit',
			TaskType::DIFFICULTY_EASY,
			[],
			[ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ],
			$excludedTemplates,
			$excludedCategories
		);
		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$configurationValidator = $this->createMock( ConfigurationValidator::class );
		$titleParser = $this->createNoOpMock( TitleParser::class );
		$handler = $this->createMock( TemplateBasedTaskSubmissionHandler::class );
		$taskTypeHandler = new TemplateBasedTaskTypeHandler(
			$configurationValidator,
			$handler,
			$titleParser
		);
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries( [ $taskType ], [] );
		$this->assertQueryStrings( $queries, [
			'-hastemplate:"Foo|Bar" -incategory:"Baz|Boom" hastemplate:"Copyedit"',
		] );
	}

	private function assertIntersectionTopicsInQueries( $queries, $topicIds ) {
		[ $query1, $query2 ] = array_values( $queries );
		foreach ( $topicIds as $id ) {
			$this->assertStringContainsString( $id, $query1->getQueryString() );
			$this->assertStringContainsString( $id, $query2->getQueryString() );
		}
	}

	private function assertTopicsInQueries( $queries, $topicIds ) {
		[ $query1, $query2 ] = array_values( $queries );
		foreach ( $topicIds as $id ) {
			if ( $query1->getTopics()[0]->getId() === $id ) {
				$this->assertSame( $query1->getTopics()[0]->getId(), $id );
			} elseif ( $query2->getTopics()[0]->getId() === $id ) {
				$this->assertSame( $query2->getTopics()[0]->getId(), $id );
			} else {
				$this->fail( "$id not found in query." );
			}
		}
	}

	private function assertTopicsInMultiTopicQueries( $queries, $topicIds ) {
		foreach ( $queries as $query ) {
			$actualTopicIds = array_reduce( array_values( $query->getTopics() ), static function ( $acc, $topic ) {
				$acc[] = $topic->getId();
				return $acc;
			}, [] );
			$diff = array_diff( $actualTopicIds, $topicIds );
			$this->assertSame( [], $diff );
		}
	}

	private function assertTaskTypeInQueries( $queries, $taskTypes ) {
		$actualTaskTypesIds = array_reduce( array_values( $queries ), static function ( $acc, $query ) {
			$acc[] = $query->getTaskType()->getId();
			return $acc;
		}, [] );
		$diff = array_diff( $actualTaskTypesIds, $taskTypes );
		$this->assertSame( [], $diff );
	}

	/**
	 * Assert that the set of $strings is the same as the set of $queries.
	 * The sets must have exactly two elements.
	 * @param array $queries
	 * @param array $expectedQueryStrings
	 */
	private function assertQueryStrings( $queries, $expectedQueryStrings ) {
		$queryStrings = array_map( static function ( SearchQuery $query ) {
			return $query->getQueryString();
		}, array_values( $queries ) );
		foreach ( $expectedQueryStrings as $expectedQueryString ) {
			if ( !in_array( $expectedQueryString, $queryStrings, true ) ) {
				$this->fail( "$expectedQueryString not found in queries:\n"
					. var_export( $queryStrings, true ) );
			}
		}
		$this->assertTrue( true );
	}

}
