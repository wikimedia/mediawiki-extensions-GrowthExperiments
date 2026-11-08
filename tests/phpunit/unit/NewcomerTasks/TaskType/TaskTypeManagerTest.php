<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeManager;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TaskTypeManager
 */
class TaskTypeManagerTest extends MediaWikiUnitTestCase {

	private WANObjectCache $cache;
	private int $editCount = 21;
	private array $topics = [];
	private array $interests = [];

	protected function setUp(): void {
		parent::setUp();
		$this->cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	/**
	 * Make a TaskTypeManager for a user who selected only link-recommendation,
	 * and who reached the edit limit of that task type.
	 */
	private function newTaskTypeManager( LevelingUpManager $levelingUpManager ): TaskTypeManager {
		$linkRecommendation = $this->createMock( LinkRecommendationTaskType::class );
		$linkRecommendation->method( 'getMaximumEditsTaskIsAvailable' )->willReturn( 20 );
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn( [
			'copyedit' => $this->createMock( TaskType::class ),
			'link-recommendation' => $linkRecommendation,
		] );

		$userOptionsLookup = $this->createMock( NewcomerTasksUserOptionsLookup::class );
		$userOptionsLookup->method( 'getTaskTypeFilter' )->willReturn( [ 'link-recommendation' ] );
		$userOptionsLookup->method( 'convertTaskTypes' )->willReturnArgument( 0 );

		$taskSetFiltersFactory = $this->createMock( TaskSetFiltersFactory::class );
		$taskSetFiltersFactory->method( 'newFromUser' )->willReturnCallback(
			fn () => new TaskSetFilters( [], $this->topics, 'OR', $this->interests )
		);

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )->willReturnCallback( fn () => $this->editCount );

		return new TaskTypeManager(
			$userOptionsLookup,
			$userEditTracker,
			$configurationLoader,
			$levelingUpManager,
			$this->cache,
			$taskSetFiltersFactory
		);
	}

	public static function provideSuggestion(): iterable {
		yield 'task type found' => [ 'copyedit', [ 'copyedit' ] ];
		yield 'no task type found' => [ null, [] ];
	}

	/**
	 * @dataProvider provideSuggestion
	 */
	public function testCachesSuggestionAcrossRequests( ?string $suggestion, array $expectedTaskTypes ) {
		$levelingUpManager = $this->createMock( LevelingUpManager::class );
		$levelingUpManager->expects( $this->once() )
			->method( 'suggestNewTaskTypeForUser' )
			->with( $this->anything(), 'link-recommendation', false, [ 'copyedit' ], true )
			->willReturn( $suggestion );
		$user = new UserIdentityValue( 1, 'Mentee' );

		// Each request makes a new TaskTypeManager.
		$this->assertSame(
			$expectedTaskTypes,
			$this->newTaskTypeManager( $levelingUpManager )->getTaskTypesForUser( $user )
		);
		$this->assertSame(
			$expectedTaskTypes,
			$this->newTaskTypeManager( $levelingUpManager )->getTaskTypesForUser( $user )
		);
	}

	public static function provideChangedInput(): iterable {
		yield 'new edit' => [ 22, [], [] ];
		yield 'new topics' => [ 21, [ 'art' ], [] ];
		yield 'new interests' => [ 21, [], [ 'Mona Lisa' ] ];
	}

	/**
	 * @dataProvider provideChangedInput
	 */
	public function testRecomputesSuggestionWhenInputChanges(
		int $editCount,
		array $topics,
		array $interests
	) {
		$levelingUpManager = $this->createMock( LevelingUpManager::class );
		$levelingUpManager->expects( $this->exactly( 2 ) )
			->method( 'suggestNewTaskTypeForUser' )
			->willReturn( 'copyedit' );
		$user = new UserIdentityValue( 1, 'Mentee' );

		$this->newTaskTypeManager( $levelingUpManager )->getTaskTypesForUser( $user );
		$this->editCount = $editCount;
		$this->topics = $topics;
		$this->interests = $interests;
		$this->newTaskTypeManager( $levelingUpManager )->getTaskTypesForUser( $user );
	}

	public function testDoesNotShareSuggestionBetweenUsers() {
		$levelingUpManager = $this->createMock( LevelingUpManager::class );
		$levelingUpManager->expects( $this->exactly( 2 ) )
			->method( 'suggestNewTaskTypeForUser' )
			->willReturn( 'copyedit' );

		$this->newTaskTypeManager( $levelingUpManager )
			->getTaskTypesForUser( new UserIdentityValue( 1, 'Mentee' ) );
		$this->newTaskTypeManager( $levelingUpManager )
			->getTaskTypesForUser( new UserIdentityValue( 2, 'Other mentee' ) );
	}
}
