<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

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
			$this->getMockConnectionProvider( $pageMap )
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
		$this->assertArrayEquals( [ 'Page1', 'Page3', 'Page5' ], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
		$this->assertSame( 8, $filteredTaskSet->getTotalCount() );
		$this->assertSame( 5, $filteredTaskSet->getOffset() );
		$this->assertSame( [ 'x' ], $filteredTaskSet->getDebugData() );

		$filteredTaskSet = $filter->filter( $taskSet, 0 );
		$this->assertArrayEquals( [], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
		$filteredTaskSet = $filter->filter( $taskSet, 2 );
		$this->assertArrayEquals( [ 'Page1', 'Page3' ], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
		$filteredTaskSet = $filter->filter( $taskSet, 6 );
		$this->assertArrayEquals( [ 'Page1', 'Page3', 'Page5' ], array_map( static function ( Task $task ) {
			return $task->getTitle()->getDBkey();
		}, iterator_to_array( $filteredTaskSet ) ) );
	}

	/**
	 * @param array[] $map "<ns>:<title>" => [ exists, is protected ]
	 * @return TitleFactory|MockObject
	 */
	private function getMockTitleFactory( array $map ) {
		$factory = $this->createNoOpMock( TitleFactory::class, [ 'newFromLinkTarget' ] );
		$factory->method( 'newFromLinkTarget' )->willReturnCallback(
			function ( LinkTarget $target ) use ( $map ) {
				$this->assertArrayHasKey( $target->getNamespace() . ':' . $target->getDBkey(), $map );
				$data = $map[$target->getNamespace() . ':' . $target->getDBkey()];
				$title = $this->createNoOpMock( Title::class,
					[ 'exists', 'getNamespace', 'getDBkey', 'getArticleID' ] );
				$title->method( 'exists' )->willReturn( $data[0] );
				$title->method( 'getNamespace' )->willReturn( $target->getNamespace() );
				$title->method( 'getDBkey' )->willReturn( $target->getDBkey() );
				$title->method( 'getArticleID' )->willReturn( str_replace( 'Page', '', $target->getDBkey() ) );
				return $title;
			} );
		return $factory;
	}

	/**
	 * @return LinkBatchFactory|MockObject
	 */
	protected function getMockLinkBatchFactory() {
		return $this->createNoOpMock( LinkBatchFactory::class, [ 'newLinkBatch' ] );
	}

	/**
	 * @param array[] $map "<ns>:<title>" => [ exists, is protected ]
	 * @return IConnectionProvider
	 */
	protected function getMockConnectionProvider( array $map ): IConnectionProvider {
		$dbr = $this->createMock( IReadableDatabase::class );
		$dbr->expects( $this->exactly( 4 ) )
			->method( 'select' )
			->with( [ 'page_restrictions' ] )
			->willReturnCallback(
				static function ( $table, $vars, $conds ) use ( $map ) {
					$data = [];
					$ids = $conds['pr_page'];
					foreach ( $ids as $id ) {
						// Ugly hack to get the reuse the $pageMap definition declaration above
						$key = "0:Page$id";
						// $map[$key][1] means that the article is protected, see $pageMap
						if ( isset( $map[$key] ) && $map[$key][1] ) {
							$data[] = (object)[ 'pr_page' => $id ];
						}
					}
					return new FakeResultWrapper( $data );
				} );
		$dbr->method( 'newSelectQueryBuilder' )
			->willReturnCallback( static function () use ( $dbr ) {
				return new SelectQueryBuilder( $dbr );
			} );
		$connProvider = $this->createMock( IConnectionProvider::class );
		$connProvider->method( 'getReplicaDatabase' )->willReturn( $dbr );
		return $connProvider;
	}

}
