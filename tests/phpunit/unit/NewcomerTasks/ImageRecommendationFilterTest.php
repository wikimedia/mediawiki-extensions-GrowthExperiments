<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\ImageRecommendationFilter
 */
class ImageRecommendationFilterTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			ImageRecommendationFilter::class,
			new ImageRecommendationFilter(
				$this->createMock( WANObjectCache::class )
			)
		);
	}

	/**
	 * @covers ::filter
	 */
	public function testFilter() {
		$taskSet = $this->getDefaultTaskSet();

		$cacheBag = new HashBagOStuff();
		$wanObjectCache = new WANObjectCache( [ 'cache' => $cacheBag ] );
		$wanObjectCache->set(
			ImageRecommendationFilter::makeKey( $wanObjectCache, 'image-recommendation', 'Task3' ),
			true
		);
		$imageRecommendationFilter = new ImageRecommendationFilter( $wanObjectCache );
		$filteredTaskSet = $imageRecommendationFilter->filter( $taskSet );
		$this->assertEquals( 2, $filteredTaskSet->getTotalCount() );
		$this->assertCount( 1, $filteredTaskSet->getInvalidTasks() );

		$wanObjectCache->delete(
			ImageRecommendationFilter::makeKey( $wanObjectCache, 'image-recommendation', 'Task3' ),
			true
		);
		$filteredTaskSet = $imageRecommendationFilter->filter( $taskSet );
		$this->assertEquals( 3, $filteredTaskSet->getTotalCount() );
		$this->assertCount( 0, $filteredTaskSet->getInvalidTasks() );
	}

	private function getDefaultTaskSet(): TaskSet {
		$copyEditTaskType = new TemplateBasedTaskType( 'copyedit', 'easy', [], [] );
		$imageRecommendationTaskType = new ImageRecommendationTaskType( 'image-recommendation', 'medium', [] );
		$task1 = new Task( $copyEditTaskType, new TitleValue( NS_MAIN, 'Task1' ) );
		$task2 = new Task( $copyEditTaskType, new TitleValue( NS_MAIN, 'Task2' ) );
		$task3 = new Task( $imageRecommendationTaskType, new TitleValue( NS_MAIN, 'Task3' ) );
		return new TaskSet(
			[ $task1, $task2, $task3 ],
			3,
			0,
			new TaskSetFilters( [ 'copyedit', 'image-recommendation' ], [] )
		);
	}
}
