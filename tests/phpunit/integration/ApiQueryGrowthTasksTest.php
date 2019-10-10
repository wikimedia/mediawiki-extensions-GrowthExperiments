<?php

namespace GrowthExperiments\Tests;

use ApiRawMessage;
use ApiTestCase;
use ApiUsageException;
use GrowthExperiments\NewcomerTasks\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use StatusValue;
use TitleValue;
use User;

/**
 * @group API
 * @group medium
 * @covers \GrowthExperiments\Api\ApiQueryGrowthTasks
 */
class ApiQueryGrowthTasksTest extends ApiTestCase {

	public function testExecute() {
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$taskType3 = new TaskType( 'update', TaskType::DIFFICULTY_MEDIUM );
		$suggester = new StaticTaskSuggester( [
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-1' ) ),
			new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-1' ) ),
			new Task( $taskType3, new TitleValue( NS_MAIN, 'Update-1' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-2' ) ),
			new Task( $taskType3, new TitleValue( NS_MAIN, 'Update-2' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-3' ) ),
		] );
		$this->setService( 'GrowthExperimentsEditSuggester', $suggester );

		$baseParams = [
			'action' => 'query',
			'list' => 'growthtasks',
		];

		list( $data ) = $this->doApiRequest( $baseParams );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertSame( [ 'title' => 'Copyedit-1', 'tasktype' => 'copyedit', 'difficulty' => 'easy' ],
			$data['query']['growthtasks']['suggestions'][0] );
		$this->assertResponseContainsTitles( [ 'Copyedit-1', 'Link-1', 'Update-1', 'Copyedit-2',
			'Update-2', 'Copyedit-3' ], $data );

		list( $data ) = $this->doApiRequest( $baseParams + [ 'gttasktypes' => 'update|link' ] );
		$this->assertResponseContainsTitles( [ 'Link-1', 'Update-1', 'Update-2' ], $data );
		$this->assertSame( 3, $data['query']['growthtasks']['totalCount'] );

		list( $data ) = $this->doApiRequest( $baseParams + [ 'gtlimit' => '2', 'gtoffset' => 3 ] );
		$this->assertResponseContainsTitles( [ 'Copyedit-2', 'Update-2' ], $data );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertSame( 5, $data['continue']['gtoffset'] );

		list( $data ) = $this->doApiRequest( $baseParams + [ 'gtlimit' => '2', 'gtoffset' => 4 ] );
		$this->assertResponseContainsTitles( [ 'Update-2', 'Copyedit-3' ], $data );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertArrayNotHasKey( 'continue', $data );
	}

	public function testExecuteGenerator() {
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$suggester = new StaticTaskSuggester( [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Task-1' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Task-2' ) ),
		] );
		$this->setService( 'GrowthExperimentsEditSuggester', $suggester );

		$baseParams = [
			'action' => 'query',
			'generator' => 'growthtasks',
		];
		list( $data ) = $this->doApiRequest( [ 'action' => 'query', 'generator' => 'growthtasks' ] );
		$this->assertSame( 2, $data['growthtasks']['totalCount'] );
		$this->assertSame( [ 'ns' => 0, 'title' => 'Task-1', 'missing' => true, 'tasktype' => 'copyedit',
			'difficulty' => TaskType::DIFFICULTY_EASY ], reset( $data['query']['pages'] ) );
	}

	public function testError() {
		$suggester = new ErrorForwardingTaskSuggester(
			StatusValue::newFatal( new ApiRawMessage( 'foo' ) ) );
		$this->setService( 'GrowthExperimentsEditSuggester', $suggester );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'foo' );

		$this->doApiRequest( [ 'action' => 'query', 'list' => 'growthtasks' ] );
	}

	public function testMustBeLoggedIn() {
		$suggester = new StaticTaskSuggester( [] );
		$this->setService( 'GrowthExperimentsEditSuggester', $suggester );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You must be logged in.' );

		$this->doApiRequest( [ 'action' => 'query', 'list' => 'growthtasks' ],
			null, null, new User() );
	}

	/**
	 * @param string[] $titles
	 * @param array $response
	 */
	protected function assertResponseContainsTitles( array $titles, array $response ) {
		$this->assertSame( $titles, array_map( function ( $item ) {
			return $item['title'];
		}, $response['query']['growthtasks']['suggestions'] ) );
	}

}
