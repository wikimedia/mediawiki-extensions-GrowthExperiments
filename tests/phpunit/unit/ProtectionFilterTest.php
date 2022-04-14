<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\RestrictionStore;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Title;
use TitleFactory;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ProtectionFilter
 */
class ProtectionFilterTest extends MediaWikiUnitTestCase {

	public function testFilter() {
		$pageMap = [
			// ns:title => [ exists, is protected ]
			'0:Page1' => [ false, false ],
			'0:Page2' => [ false, true ],
			'0:Page3' => [ true, false ],
			'0:Page4' => [ true, true ],
			'0:Page5' => [ true, false ],
		];
		$filter = new ProtectionFilter(
			$this->getMockTitleFactory( $pageMap ),
			$this->getMockLinkBatchFactory(),
			$this->getMockRestrictionStore( $pageMap )
		);
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_EASY );
		$taskSet = new TaskSet( [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Page1' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Page2' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Page3' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Page4' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Page5' ) ),
		], 10, 5, new TaskSetFilters() );
		$taskSet->setDebugData( [ 'x' ] );

		$filteredTaskSet = $filter->filter( $taskSet );
		$this->assertArrayEquals( [ 'Page1', 'Page2', 'Page3', 'Page5' ], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
		$this->assertSame( 9, $filteredTaskSet->getTotalCount() );
		$this->assertSame( 5, $filteredTaskSet->getOffset() );
		$this->assertSame( [ 'x' ], $filteredTaskSet->getDebugData() );

		$filteredTaskSet = $filter->filter( $taskSet, 0 );
		$this->assertArrayEquals( [], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
		$filteredTaskSet = $filter->filter( $taskSet, 2 );
		$this->assertArrayEquals( [ 'Page1', 'Page2' ], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
		$filteredTaskSet = $filter->filter( $taskSet, 6 );
		$this->assertArrayEquals( [ 'Page1', 'Page2', 'Page3', 'Page5' ], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
	}

	/**
	 * @param array[] $map "<ns>:<title>" => [ exists, is protected ]
	 * @return TitleFactory|MockObject
	 */
	private function getMockTitleFactory( array $map ) {
		$factory = $this->getMockBuilder( TitleFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'newFromLinkTarget' ] )
			->getMock();
		$factory->method( 'newFromLinkTarget' )->willReturnCallback(
			function ( LinkTarget $target ) use ( $map ) {
				$this->assertArrayHasKey( $target->getNamespace() . ':' . $target->getDBkey(), $map );
				$data = $map[$target->getNamespace() . ':' . $target->getDBkey()];
				$title = $this->getMockBuilder( Title::class )
					->disableOriginalConstructor()
					->onlyMethods( [ 'exists', 'getNamespace', 'getDBkey' ] )
					->getMock();
				$title->method( 'exists' )->willReturn( $data[0] );
				$title->method( 'getNamespace' )->willReturn( $target->getNamespace() );
				$title->method( 'getDBkey' )->willReturn( $target->getDBkey() );
				return $title;
			} );
		return $factory;
	}

	/**
	 * @return LinkBatchFactory|MockObject
	 */
	protected function getMockLinkBatchFactory() {
		return $this->getMockBuilder( LinkBatchFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'newLinkBatch' ] )
			->getMock();
	}

	/**
	 * @param array[] $map "<ns>:<title>" => [ exists, is protected ]
	 * @return RestrictionStore|MockObject
	 */
	protected function getMockRestrictionStore( array $map ) {
		$restrictionStore = $this->getMockBuilder( RestrictionStore::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'isProtected' ] )
			->getMock();
		$restrictionStore->method( 'isProtected' )->willReturnCallback(
			function ( PageIdentity $page ) use ( $map ) {
				$this->assertArrayHasKey( $page->getNamespace() . ':' . $page->getDBkey(), $map );
				$data = $map[$page->getNamespace() . ':' . $page->getDBkey()];
				return $data[1];
			} );
		return $restrictionStore;
	}

}
