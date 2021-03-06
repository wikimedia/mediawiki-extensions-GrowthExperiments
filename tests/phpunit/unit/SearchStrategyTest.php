<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy
 * FIXME part of SearchStrategy is tested in RemoteSearchTaskSuggesterTest
 */
class SearchStrategyTest extends MediaWikiUnitTestCase {

	public function testGetQueries() {
		$taskType = new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY,
			[], [ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ] );
		$morelikeTopic1 = new MorelikeBasedTopic( 'art', [
			new TitleValue( NS_MAIN, 'Picasso' ),
			new TitleValue( NS_MAIN, 'Watercolor' ),
		] );
		$morelikeTopic2 = new MorelikeBasedTopic( 'science', [
			new TitleValue( NS_MAIN, 'Einstein' ),
			new TitleValue( NS_MAIN, 'Physics' ),
		] );
		$oresTopic1 = new OresBasedTopic( 'art', 'culture', [ 'painting', 'drawing' ] );
		$oresTopic2 = new OresBasedTopic( 'science', 'stem', [ 'physics', 'biology' ] );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturn( 'hastemplate:"Copyedit"' );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry,
			new StaticConfigurationLoader( [], [] ) );

		$morelikeQueries = $searchStrategy->getQueries( [ $taskType ],
			[ $morelikeTopic1, $morelikeTopic2 ] );
		$this->assertCount( 2, $morelikeQueries );

		$this->assertTopicsInQueries( $morelikeQueries, [ 'art', 'science' ] );
		$this->assertTaskTypeInQueries( $morelikeQueries, [ 'copyedit' ] );

		$this->assertQueryStrings( $morelikeQueries, [
			'hastemplate:"Copyedit" morelikethis:"Picasso|Watercolor"',
			'hastemplate:"Copyedit" morelikethis:"Einstein|Physics"' ] );

		$oresQueries = $searchStrategy->getQueries( [ $taskType ], [ $oresTopic1, $oresTopic2 ], [] );
		$this->assertCount( 2, $oresQueries );
		$this->assertTaskTypeInQueries( $oresQueries, [ 'copyedit' ] );
		$this->assertTopicsInQueries( $oresQueries, [ 'art', 'science' ] );
		$this->assertQueryStrings( $oresQueries, [
			'hastemplate:"Copyedit" articletopic:painting|drawing',
			'hastemplate:"Copyedit" articletopic:physics|biology'
		] );

		$restrictedQueries = $searchStrategy->getQueries( [ $taskType ],
			[ $oresTopic1, $oresTopic2 ], [ 1, 2, 3 ] );
		$this->assertCount( 2, $restrictedQueries );
		$this->assertTopicsInQueries( $restrictedQueries, [ 'art', 'science' ] );
		$this->assertQueryStrings( $restrictedQueries, [
			'hastemplate:"Copyedit" articletopic:painting|drawing pageid:1|2|3',
			'hastemplate:"Copyedit" articletopic:physics|biology pageid:1|2|3'
		] );
	}

	public function testExclusion() {
		$taskType = new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY,
			[], [ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ] );
		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturn( 'hastemplate:"Copyedit"' );

		$excludedTemplates = [
			new TitleValue( NS_TEMPLATE, 'Foo' ),
			new TitleValue( NS_TEMPLATE, 'Bar' ),
		];
		$excludedCategories = [
			new TitleValue( NS_CATEGORY, 'Baz' ),
			new TitleValue( NS_CATEGORY, 'Boom' ),
		];
		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry,
			new StaticConfigurationLoader( [], [], $excludedTemplates, $excludedCategories ) );

		$queries = $searchStrategy->getQueries( [ $taskType ], [] );
		$this->assertQueryStrings( $queries, [
			'hastemplate:"Copyedit" -hastemplate:"Foo|Bar" -incategory:"Baz|Boom"',
		] );
	}

	private function assertTopicsInQueries( $queries, $topicIds ) {
		list( $query1, $query2 ) = array_values( $queries );
		foreach ( $topicIds as $id ) {
			if ( $query1->getTopic()->getId() === $id ) {
				$this->assertSame( $query1->getTopic()->getId(), $id );
			} elseif ( $query2->getTopic()->getId() === $id ) {
				$this->assertSame( $query2->getTopic()->getId(), $id );
			} else {
				$this->assertTrue( false, "$id not found in query." );
			}
		}
	}

	private function assertTaskTypeInQueries( $queries, $taskTypes ) {
		list( $query1, $query2 ) = array_values( $queries );
		foreach ( $taskTypes as $id ) {
			if ( $query1->getTaskType()->getId() === $id ) {
				$this->assertSame( $query1->getTaskType()->getId(), $id );
			} elseif ( $query2->getTaskType()->getId() === $id ) {
				$this->assertSame( $query2->getTaskType()->getId(), $id );
			} else {
				$this->assertTrue( false, "$id not found in query." );
			}
		}
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
				$this->assertTrue( false, "$expectedQueryString not found in queries:\n"
					. var_export( $queryStrings, true ) );
			}
		}
		$this->assertTrue( true );
	}

}
