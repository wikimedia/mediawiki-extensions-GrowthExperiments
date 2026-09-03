<?php
declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\NewcomerTasksCacheRefreshJob;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use LogicException;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\NewcomerTasksCacheRefreshJob
 */
class NewcomerTasksCacheRefreshJobTest extends MediaWikiUnitTestCase {

	public function testRunSuggestsWithTheFiltersFromTheFactory(): void {
		$user = new UserIdentityValue( 1, 'User1' );
		$filters = new TaskSetFilters( [ 'copyedit' ], [ 'ores' ] );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByUserId' )
			->with( 1 )
			->willReturn( $user );
		$taskSetFiltersFactory = $this->createMock( TaskSetFiltersFactory::class );
		$taskSetFiltersFactory->method( 'newFromUser' )
			->with( $user )
			->willReturn( $filters );
		$taskSuggester = $this->createMock( TaskSuggester::class );
		$taskSuggester->expects( $this->once() )
			->method( 'suggest' )
			->with( $user, $filters, SearchTaskSuggester::DEFAULT_LIMIT, null, [ 'useCache' => false ] )
			->willReturn( new TaskSet( [], 0, 0, $filters ) );
		$taskSuggesterFactory = $this->createMock( TaskSuggesterFactory::class );
		$taskSuggesterFactory->method( 'create' )->willReturn( $taskSuggester );

		$job = new NewcomerTasksCacheRefreshJob(
			[ 'userId' => 1 ],
			$userIdentityLookup,
			$taskSetFiltersFactory,
			$taskSuggesterFactory
		);

		$this->assertTrue( $job->run() );
	}

	public function testRunThrowsForUnknownUser(): void {
		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByUserId' )->willReturn( null );
		$taskSuggesterFactory = $this->createMock( TaskSuggesterFactory::class );
		$taskSuggesterFactory->method( 'create' )->willReturn( $this->createNoOpMock( TaskSuggester::class ) );

		$job = new NewcomerTasksCacheRefreshJob(
			[ 'userId' => 42 ],
			$userIdentityLookup,
			$this->createNoOpMock( TaskSetFiltersFactory::class ),
			$taskSuggesterFactory
		);

		$this->expectException( LogicException::class );
		$job->run();
	}

}
