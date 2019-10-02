<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task;
use GrowthExperiments\NewcomerTasks\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester
 */
class StaticTaskSuggesterTest extends MediaWikiUnitTestCase {

	public function testSuggest() {
		$user = new UserIdentityValue( 1, 'Foo', 1 );
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$suggester = new StaticTaskSuggester( [
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-1' ) ),
			new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-1' ) ),
			new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-2' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-2' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-3' ) ),
			new Task( $taskType2, new TitleValue( NS_MAIN, 'Link-3' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-4' ) ),
			new Task( $taskType1, new TitleValue( NS_MAIN, 'Copyedit-5' ) ),
		] );

		$taskSet = $suggester->suggest( $user );
		$this->assertInstanceOf( TaskSet::class, $taskSet );
		$this->assertTaskSetEqualsTitles( [ 'Copyedit-1', 'Link-1', 'Link-2', 'Copyedit-2',
			'Copyedit-3', 'Link-3', 'Copyedit-4', 'Copyedit-5' ], $taskSet );
		$this->assertSame( 8, $taskSet->getTotalCount() );
		$this->assertSame( 0, $taskSet->getOffset() );

		$taskSet = $suggester->suggest( $user, null, null, 3, 0 );
		$this->assertTaskSetEqualsTitles( [ 'Copyedit-1', 'Link-1', 'Link-2' ], $taskSet );
		$this->assertSame( 8, $taskSet->getTotalCount() );
		$this->assertSame( 0, $taskSet->getOffset() );

		$taskSet = $suggester->suggest( $user, null, null, 3, 6 );
		$this->assertTaskSetEqualsTitles( [ 'Copyedit-4', 'Copyedit-5' ], $taskSet );
		$this->assertSame( 8, $taskSet->getTotalCount() );
		$this->assertSame( 6, $taskSet->getOffset() );

		$taskSet = $suggester->suggest( $user, [ 'copyedit' ] );
		$this->assertTaskSetEqualsTitles( [ 'Copyedit-1', 'Copyedit-2', 'Copyedit-3',
			'Copyedit-4', 'Copyedit-5' ], $taskSet );
		$this->assertSame( 5, $taskSet->getTotalCount() );
		$this->assertSame( 0, $taskSet->getOffset() );

		$taskSet = $suggester->suggest( $user, [ 'copyedit' ], null, 2, 2 );
		$this->assertTaskSetEqualsTitles( [ 'Copyedit-3', 'Copyedit-4' ], $taskSet );
		$this->assertSame( 5, $taskSet->getTotalCount() );
		$this->assertSame( 2, $taskSet->getOffset() );
	}

	/**
	 * @param string[] $expectedTitles
	 * @param TaskSet $taskSet
	 */
	protected function assertTaskSetEqualsTitles( array $expectedTitles, TaskSet $taskSet ) {
		$actualTitles = array_map( function ( Task $task ) {
			return $task->getTitle()->getText();
		}, iterator_to_array( $taskSet ) );
		$this->assertSame( $expectedTitles, $actualTitles );
	}

}
