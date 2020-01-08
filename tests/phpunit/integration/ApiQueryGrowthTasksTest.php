<?php

namespace GrowthExperiments\Tests;

use ApiRawMessage;
use ApiTestCase;
use ApiUsageException;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
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
		$configurationLoader = new StaticConfigurationLoader( [ $taskType1, $taskType2, $taskType3 ] );
		$this->setService( 'GrowthExperimentsTaskSuggester', $suggester );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );

		$baseParams = [
			'action' => 'query',
			'list' => 'growthtasks',
		];

		list( $data ) = $this->doApiRequest( $baseParams );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertSame( [ 'title' => 'Copyedit-1', 'tasktype' => 'copyedit', 'difficulty' => 'easy',
			'order' => 0 ], $data['query']['growthtasks']['suggestions'][0] );
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
		$configurationLoader = new StaticConfigurationLoader( [ $taskType ] );
		$this->setService( 'GrowthExperimentsTaskSuggester', $suggester );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );

		list( $data ) = $this->doApiRequest( [ 'action' => 'query', 'generator' => 'growthtasks' ] );
		$this->assertSame( 2, $data['growthtasks']['totalCount'] );
		$this->assertSame( [ 'ns' => 0, 'title' => 'Task-1', 'missing' => true, 'tasktype' => 'copyedit',
			'difficulty' => TaskType::DIFFICULTY_EASY, 'order' => 0 ], reset( $data['query']['pages'] ) );
	}

	public function testError() {
		$suggester = new ErrorForwardingTaskSuggester(
			StatusValue::newFatal( new ApiRawMessage( 'foo' ) ) );
		$configurationLoader = new StaticConfigurationLoader( [] );
		$this->setService( 'GrowthExperimentsTaskSuggester', $suggester );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'foo' );

		$this->doApiRequest( [ 'action' => 'query', 'list' => 'growthtasks' ] );
	}

	public function testMustBeLoggedIn() {
		$suggester = new StaticTaskSuggester( [] );
		$configurationLoader = new StaticConfigurationLoader( [] );
		$this->setService( 'GrowthExperimentsTaskSuggester', $suggester );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You must be logged in.' );

		$this->doApiRequest( [ 'action' => 'query', 'list' => 'growthtasks' ],
			null, null, new User() );
	}

	public function testGetAllowedParams() {
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$topic1 = new Topic( 'art' );
		$topic2 = new Topic( 'science' );
		$suggester = new StaticTaskSuggester( [] );
		$configurationLoader = new StaticConfigurationLoader( [ $taskType1, $taskType2 ],
			[ $topic1, $topic2 ] );
		$this->setService( 'GrowthExperimentsTaskSuggester', $suggester );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );

		list( $data ) = $this->doApiRequest( [ 'action' => 'paraminfo',
			'modules' => 'query+growthtasks' ] );
		$this->assertArrayHasKey( 'paraminfo', $data );
		$this->assertArrayHasKey( 0, $data['paraminfo']['modules'] );
		$this->assertSame( 'growthtasks', $data['paraminfo']['modules'][0]['name'] );
		$this->assertArrayHasKey( 1, $data['paraminfo']['modules'][0]['parameters'] );
		$this->assertSame( 'tasktypes', $data['paraminfo']['modules'][0]['parameters'][0]['name'] );
		$this->assertSame( 'topics', $data['paraminfo']['modules'][0]['parameters'][1]['name'] );
		$this->assertSame( [ 'copyedit', 'link' ],
			$data['paraminfo']['modules'][0]['parameters'][0]['type'] );
		$this->assertSame( [ 'art', 'science' ],
			$data['paraminfo']['modules'][0]['parameters'][1]['type'] );
		$this->assertArrayHasKey( 'paraminfo', $data );

		// Make sure loading errors do not break parameter info
		$suggester = new StaticTaskSuggester( [] );
		$configurationLoader = new StaticConfigurationLoader( StatusValue::newFatal( 'foo' ),
			StatusValue::newFatal( 'bar' ) );
		$this->setService( 'GrowthExperimentsTaskSuggester', $suggester );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );
		list( $data ) = $this->doApiRequest( [ 'action' => 'paraminfo',
			'modules' => 'query+growthtasks' ] );
		$this->assertArrayHasKey( 'paraminfo', $data );
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
