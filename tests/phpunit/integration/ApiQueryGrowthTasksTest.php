<?php

namespace GrowthExperiments\Tests;

use ApiRawMessage;
use ApiTestCase;
use ApiUsageException;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use StatusValue;
use TitleValue;

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
		$suggesterFactory = new StaticTaskSuggesterFactory( [
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-1' ) ),
			new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-1' ) ),
			new Task( $taskType3, new TitleValue( NS_MAIN, 'Update-1' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-2' ) ),
			new Task( $taskType3, new TitleValue( NS_MAIN, 'Update-2' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-3' ) ),
		] );
		$configurationLoader = new StaticConfigurationLoader( [ $taskType1, $taskType2, $taskType3 ] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		$baseParams = [
			'action' => 'query',
			'list' => 'growthtasks',
		];

		list( $data ) = $this->doApiRequest( $baseParams );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertSame( 'Copyedit-1', $data['query']['growthtasks']['suggestions'][0]['title'] );
		$this->assertSame( 'copyedit', $data['query']['growthtasks']['suggestions'][0]['tasktype'] );
		$this->assertSame( 'easy', $data['query']['growthtasks']['suggestions'][0]['difficulty'] );
		$this->assertSame( 0, $data['query']['growthtasks']['suggestions'][0]['order'] );
		$this->assertSame( [], $data['query']['growthtasks']['suggestions'][0]['qualityGateIds'] );
		$this->assertSame( [], $data['query']['growthtasks']['suggestions'][0]['qualityGateConfig'] );
		$this->assertRegExp( "/^[a-z0-9]{32}+$/", $data['query']['growthtasks']['suggestions'][0]['token'] );

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
		$suggesterFactory = new StaticTaskSuggesterFactory( [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Task-1' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Task-2' ) ),
		] );
		$configurationLoader = new StaticConfigurationLoader( [ $taskType ] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		list( $data ) = $this->doApiRequest( [ 'action' => 'query', 'generator' => 'growthtasks' ] );
		$pages = reset( $data['query']['pages'] );
		$this->assertSame( 2, $data['growthtasks']['totalCount'] );
		$this->assertSame( 0, $pages['ns'] );
		$this->assertSame( 'Task-1', $pages['title'] );
		$this->assertSame( true, $pages['missing'] );
		$this->assertSame( 'copyedit', $pages['tasktype'] );
		$this->assertSame( TaskType::DIFFICULTY_EASY, $pages['difficulty'] );
		$this->assertSame( 0, $pages['order'] );
		$this->assertSame( [], $pages['qualityGateIds'] );
		$this->assertSame( [], $pages['qualityGateConfig'] );
		$this->assertRegExp( "/^[a-z0-9]{32}+$/", $pages['token'] );
	}

	public function testError() {
		$suggesterFactory = new StaticTaskSuggesterFactory( new ErrorForwardingTaskSuggester(
			StatusValue::newFatal( new ApiRawMessage( 'foo' ) ) ) );
		$configurationLoader = new StaticConfigurationLoader( [] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'foo' );

		$this->doApiRequest( [ 'action' => 'query', 'list' => 'growthtasks' ] );
	}

	public function testGetAllowedParams() {
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$topic1 = new Topic( 'art' );
		$topic2 = new Topic( 'science' );
		$suggesterFactory = new StaticTaskSuggesterFactory( [] );
		$configurationLoader = new StaticConfigurationLoader( [ $taskType1, $taskType2 ],
			[ $topic1, $topic2 ] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

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
		$suggesterFactory = new StaticTaskSuggesterFactory( [] );
		$configurationLoader = new StaticConfigurationLoader( StatusValue::newFatal( 'foo' ),
			StatusValue::newFatal( 'bar' ) );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		list( $data ) = $this->doApiRequest( [ 'action' => 'paraminfo',
			'modules' => 'query+growthtasks' ] );
		$this->assertArrayHasKey( 'paraminfo', $data );
	}

	/**
	 * @param string[] $titles
	 * @param array $response
	 */
	protected function assertResponseContainsTitles( array $titles, array $response ) {
		$this->assertSame( $titles, array_map( static function ( $item ) {
			return $item['title'];
		}, $response['query']['growthtasks']['suggestions'] ) );
	}

}
