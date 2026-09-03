<?php
declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory
 */
class TaskSetFiltersFactoryTest extends MediaWikiUnitTestCase {

	public function testNewFromUserUsesThePreferences(): void {
		$user = new UserIdentityValue( 1, 'User1' );
		$factory = new TaskSetFiltersFactory( $this->getUserOptionsLookup() );

		$this->assertEquals(
			new TaskSetFilters( [ 'copyedit' ], [ 'ores' ], SearchStrategy::TOPIC_MATCH_MODE_AND ),
			$factory->newFromUser( $user )
		);
	}

	public function testNewFromUserWithExplicitTaskTypes(): void {
		$user = new UserIdentityValue( 1, 'User1' );
		$userOptionsLookup = $this->getUserOptionsLookup();
		$userOptionsLookup->expects( $this->never() )->method( 'getTaskTypeFilter' );
		$factory = new TaskSetFiltersFactory( $userOptionsLookup );

		$this->assertEquals(
			new TaskSetFilters( [ 'links' ], [ 'ores' ], SearchStrategy::TOPIC_MATCH_MODE_AND ),
			$factory->newFromUser( $user, [ 'links' ] )
		);
	}

	private function getUserOptionsLookup(): NewcomerTasksUserOptionsLookup&MockObject {
		$userOptionsLookup = $this->createMock( NewcomerTasksUserOptionsLookup::class );
		$userOptionsLookup->method( 'getTaskTypeFilter' )->willReturn( [ 'copyedit' ] );
		$userOptionsLookup->method( 'getTopics' )->willReturn( [ 'ores' ] );
		$userOptionsLookup->method( 'getTopicsMatchMode' )
			->willReturn( SearchStrategy::TOPIC_MATCH_MODE_AND );
		return $userOptionsLookup;
	}

}
