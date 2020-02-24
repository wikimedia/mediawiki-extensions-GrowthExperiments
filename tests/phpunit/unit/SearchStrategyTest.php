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
		list( $query1, $query2 ) = array_values( $morelikeQueries );
		$this->assertSame( 'copyedit', $query1->getTaskType()->getId() );
		$this->assertSame( 'art', $query1->getTopic()->getId() );
		$this->assertSame( 'science', $query2->getTopic()->getId() );
		$this->assertSame( 'hastemplate:"Copyedit" morelikethis:"Picasso|Watercolor"',
			$query1->getQueryString() );
		$this->assertSame( 'hastemplate:"Copyedit" morelikethis:"Einstein|Physics"',
			$query2->getQueryString() );

		$oresQueries = $searchStrategy->getQueries( [ $taskType ], [ $oresTopic1, $oresTopic2 ], [] );
		$this->assertCount( 2, $oresQueries );
		list( $query1, $query2 ) = array_values( $oresQueries );
		$this->assertSame( 'copyedit', $query1->getTaskType()->getId() );
		$this->assertSame( 'art', $query1->getTopic()->getId() );
		$this->assertSame( 'science', $query2->getTopic()->getId() );
		$this->assertSame( 'hastemplate:"Copyedit" articletopic:painting|drawing',
			$query1->getQueryString() );
		$this->assertSame( 'hastemplate:"Copyedit" articletopic:physics|biology',
			$query2->getQueryString() );
	}

}
