<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
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
		$searchStrategy = new SearchStrategy();

		$morelikeQueries = $searchStrategy->getQueries( [ $taskType ],
			[ $morelikeTopic1, $morelikeTopic2 ], [] );
		$this->assertCount( 2, $morelikeQueries );

		$this->assertTopicsInQueries( $morelikeQueries, [ 'art', 'science' ] );
		$this->assertTaskTypeInQueries( $morelikeQueries, [ 'copyedit' ] );

		$this->assertTemplatesInQueries( $morelikeQueries, [
			'hastemplate:"Copyedit" morelikethis:"Picasso|Watercolor"',
			'hastemplate:"Copyedit" morelikethis:"Einstein|Physics"' ] );

		$oresQueries = $searchStrategy->getQueries( [ $taskType ], [ $oresTopic1, $oresTopic2 ], [] );
		$this->assertCount( 2, $oresQueries );
		$this->assertTaskTypeInQueries( $oresQueries, [ 'copyedit' ] );
		$this->assertTopicsInQueries( $oresQueries, [ 'art', 'science' ] );
		$this->assertTemplatesInQueries( $oresQueries, [
			'hastemplate:"Copyedit" articletopic:painting|drawing',
			'hastemplate:"Copyedit" articletopic:physics|biology'
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

	private function assertTemplatesInQueries( $queries, $templates ) {
		list( $query1, $query2 ) = array_values( $queries );
		foreach ( $templates as $template ) {
			if ( $query1->getQueryString() === $template ) {
				$this->assertSame( $query1->getQueryString(), $template );
			} elseif ( $query2->getQueryString() === $template ) {
				$this->assertSame( $query2->getQueryString(), $template );
			} else {
				$this->assertTrue( false, "$template not found in query." );
			}
		}
	}

}
