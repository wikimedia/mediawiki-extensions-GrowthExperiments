<?php

namespace GrowthExperiments\Tests\Unit\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\LinkRecommendationFilter
 */
class LinkRecommendationFilterTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			LinkRecommendationFilter::class,
			new LinkRecommendationFilter(
				$this->createMock( LinkRecommendationStore::class ) )
		);
	}

	/**
	 * @covers ::filter
	 */
	public function testFilter() {
		$taskSet = $this->getTaskSet();
		$linkRecommendationStore = $this->createMock( LinkRecommendationStore::class );
		$linkRecommendationStore->method( 'getByLinkTarget' )->willReturn(
			$this->createMock( LinkRecommendation::class )
		);
		$linkRecommendationFilter = new LinkRecommendationFilter( $linkRecommendationStore );
		$filteredTaskSet = $linkRecommendationFilter->filter( $taskSet );
		$this->assertEquals( 3, $filteredTaskSet->getTotalCount() );
		$this->assertCount( 0, $filteredTaskSet->getInvalidTasks() );
	}

	/**
	 * @covers ::filter
	 */
	public function testFilterMarkInvalid() {
		$taskSet = $this->getTaskSet();
		$linkRecommendationStore = $this->createMock( LinkRecommendationStore::class );
		$linkRecommendationStore->method( 'getByLinkTarget' )->willReturn( null );
		$linkRecommendationFilter = new LinkRecommendationFilter( $linkRecommendationStore );
		$filteredTaskSet = $linkRecommendationFilter->filter( $taskSet );
		$this->assertEquals( 2, $filteredTaskSet->getTotalCount() );
		$this->assertCount( 1, $filteredTaskSet->getInvalidTasks() );
	}

	private function getTaskSet(): TaskSet {
		$copyEditTaskType = new TemplateBasedTaskType( 'copyedit', 'easy', [], [] );
		$linkRecommendation = new LinkRecommendationTaskType( 'link-recommendation', 'easy', [] );
		$task1 = new Task( $copyEditTaskType, new \TitleValue( NS_MAIN, 'Task1' ) );
		$task2 = new Task( $copyEditTaskType, new \TitleValue( NS_MAIN, 'Task2' ) );
		$task3 = new Task( $linkRecommendation, new \TitleValue( NS_MAIN, 'Task3' ) );
		return new TaskSet(
			[ $task1, $task2, $task3 ],
			3,
			0,
			new TaskSetFilters( [ 'copyedit', 'link-recommendation' ], [] )
		);
	}

}
