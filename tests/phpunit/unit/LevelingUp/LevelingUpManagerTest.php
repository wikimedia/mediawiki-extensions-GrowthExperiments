<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\UserImpact\UserImpact;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\LevelingUp\LevelingUpManager
 */
class LevelingUpManagerTest extends MediaWikiUnitTestCase {

	public function testConstruct() {
		$this->assertInstanceOf( LevelingUpManager::class, $this->getLevelingUpManager() );
	}

	public function testGetTaskTypesGroupedByDifficulty() {
		$this->assertEquals(
			[
				'easy' => [ 'link-recommendation', 'copyedit' ],
				'medium' => [ 'update' ],
				'hard' => [ 'cx', 'newarticle' ]
			],
			$this->getLevelingUpManager()->getTaskTypesGroupedByDifficulty( [
				'link-recommendation',
				'copyedit',
				'update',
				'cx',
				'newarticle'
			] )
		);
	}

	public function testGetTaskTypesGroupedByDifficultyWithLinks() {
		$serviceOptions = new ServiceOptions(
			LevelingUpManager::CONSTRUCTOR_OPTIONS,
			new HashConfig( [
				'GELevelingUpManagerTaskTypeCountThresholdMultiple' => 5,
				'GELevelingUpManagerInvitationThresholds' => [ 3, 7 ],
				'GENewcomerTasksLinkRecommendationsEnabled' => false,
				'GELevelingUpGetStartedMaxTotalEdits' => 10,
			] )
		);
		$this->assertEquals(
			[
				'easy' => [ 'copyedit', 'links' ],
				'medium' => [ 'update' ],
				'hard' => [ 'cx', 'newarticle' ]
			],
			$this->getLevelingUpManager(
				null,
				null,
				null,
				null,
				null,
				$serviceOptions
			)->getTaskTypesGroupedByDifficulty( [
				'copyedit',
				'links',
				'update',
				'cx',
				'newarticle'
			] )
		);
	}

	public function testGetNextSuggestedTaskTypeForUser() {
		$userImpact = $this->createMock( UserImpact::class );
		$userImpact->expects( $this->once() )
			->method( 'getEditCountByTaskType' )
			->willReturn( [
				'copyedit' => 4,
				'links' => 0,
				'link-recommendation' => 0,
			] );
		$userImpactLookup = $this->getUserImpactLookup();
		$userImpactLookup->expects( $this->once() )
			->method( 'getUserImpact' )
			->willReturn( $userImpact );
		$taskSuggester = $this->createMock( TaskSuggester::class );
		$taskSet = $this->createMock( TaskSet::class );
		$taskSet->method( 'count' )
			->willReturn( 1 );
		$taskSuggester->method( 'suggest' )
			->willReturn( $taskSet );
		$taskSuggesterFactory = $this->getTaskSuggesterFactory( $taskSuggester );
		$this->assertEquals( 'link-recommendation', $this->getLevelingUpManager(
			null,
			null,
			$userImpactLookup,
			$taskSuggesterFactory
		)->suggestNewTaskTypeForUser(
			new UserIdentityValue( 1, 'Test' ),
			'copyedit',
			false,
			[
				'copyedit',
				'link-recommendation'
			]
		) );
	}

	public function testUserOptedOutOfPrompt() {
		$userImpact = $this->createMock( UserImpact::class );
		$userImpact->expects( $this->once() )
			->method( 'getEditCountByTaskType' )
			->willReturn( [
				'copyedit' => 9,
				'link-recommendation' => 0,
			] );
		$userImpactLookup = $this->getUserImpactLookup();
		$userImpactLookup->expects( $this->once() )
			->method( 'getUserImpact' )
			->willReturn( $userImpact );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userIdentity = new UserIdentityValue( 1, 'Test' );
		$userOptionsLookup->method( 'getOption' )
			->with( $userIdentity, LevelingUpManager::TASK_TYPE_PROMPT_OPT_OUTS_PREF )
			->willReturn( json_encode( [ 'copyedit' ] ) );
		$this->assertSame( null, $this->getLevelingUpManager(
			$userOptionsLookup,
			null,
			$userImpactLookup
		)->suggestNewTaskTypeForUser(
			$userIdentity,
			'copyedit'
		) );
	}

	public function testNoUserImpact() {
		$userImpactLookup = $this->getUserImpactLookup();
		$userImpactLookup->expects( $this->once() )
			->method( 'getUserImpact' )
			->willReturn( null );
		$this->assertSame( null, $this->getLevelingUpManager(
			null,
			null,
			$userImpactLookup
		)->suggestNewTaskTypeForUser(
			new UserIdentityValue( 1, 'Test' ),
			'copyedit'
		) );
	}

	public function testEveryFifthEdit() {
		$userImpact = $this->createMock( UserImpact::class );
		$userImpact->expects( $this->once() )
			->method( 'getEditCountByTaskType' )
			->willReturn( [
				'copyedit' => 9,
				'link-recommendation' => 0,
			] );
		$userImpactLookup = $this->getUserImpactLookup();
		$userImpactLookup->expects( $this->once() )
			->method( 'getUserImpact' )
			->willReturn( $userImpact );
		$taskSuggester = $this->createMock( TaskSuggester::class );
		$taskSet = $this->createMock( TaskSet::class );
		$taskSet->method( 'count' )
			->willReturn( 1 );
		$taskSuggester->method( 'suggest' )
			->willReturn( $taskSet );
		$taskSuggesterFactory = $this->getTaskSuggesterFactory( $taskSuggester );
		$this->assertSame( 'link-recommendation', $this->getLevelingUpManager(
			null,
			null,
			$userImpactLookup,
			$taskSuggesterFactory
		)->suggestNewTaskTypeForUser(
			new UserIdentityValue( 1, 'Test' ),
			'copyedit',
			false,
			[
				'copyedit',
				'link-recommendation'
			]
		) );
	}

	public function testDontSuggestIfNotFifthEdit() {
		$userImpact = $this->createMock( UserImpact::class );
		$userImpact->expects( $this->once() )
			->method( 'getEditCountByTaskType' )
			->willReturn( [
				'copyedit' => 8,
				'link-recommendation' => 0,
			] );
		$userImpactLookup = $this->getUserImpactLookup();
		$userImpactLookup->expects( $this->once() )
			->method( 'getUserImpact' )
			->willReturn( $userImpact );
		$this->assertSame( null, $this->getLevelingUpManager(
			null,
			null,
			$userImpactLookup
		)->suggestNewTaskTypeForUser(
			new UserIdentityValue( 1, 'Test' ),
			'copyedit'
		) );
	}

	public function testDontSuggestIfNoTaskAvailableInPool() {
		$userImpact = $this->createMock( UserImpact::class );
		$userImpact->expects( $this->once() )
			->method( 'getEditCountByTaskType' )
			->willReturn( [
				'copyedit' => 4,
				'link-recommendation' => 0,
				'update' => 0,
				'cx' => 1,
				'newarticle' => 2,
			] );
		$userImpactLookup = $this->getUserImpactLookup();
		$userImpactLookup->expects( $this->once() )
			->method( 'getUserImpact' )
			->willReturn( $userImpact );
		$taskSuggester = $this->createMock( TaskSuggester::class );
		$taskSet = $this->createMock( TaskSet::class );
		$taskSet->method( 'count' )
			->willReturn( 0 );
		$taskSuggester->method( 'suggest' )
			->willReturn( $taskSet );
		$taskSuggesterFactory = $this->getTaskSuggesterFactory( $taskSuggester );
		$this->assertSame( null, $this->getLevelingUpManager(
			null,
			$this->getConfigurationLoader(),
			$userImpactLookup,
			$taskSuggesterFactory
		)->suggestNewTaskTypeForUser(
			new UserIdentityValue( 1, 'Test' ),
			'copyedit',
			false,
			[
				'copyedit',
				'link-recommendation',
				'update',
				'cx',
				'newarticle',
			]
		) );
	}

	public static function provideIsEnabledForAnyone() {
		return [
			'no Suggested edits' => [ false, [
				'GEHomepageSuggestedEditsEnabled' => false,
			] ],
			'all OK' => [ true, [
				'GEHomepageSuggestedEditsEnabled' => true,
			] ],
		];
	}

	/**
	 * @dataProvider provideIsEnabledForAnyone
	 */
	public function testIsEnabledForAnyone( bool $expected, array $config ) {
		$this->assertEquals(
			$expected,
			LevelingUpManager::isEnabledForAnyone( new HashConfig( $config ) )
		);
	}

	private function getDefaultConfigValues(): array {
		return [
			'GELevelingUpManagerTaskTypeCountThresholdMultiple' => 5,
			'GELevelingUpManagerInvitationThresholds' => [ 3, 7 ],
			'GELevelingUpGetStartedMaxTotalEdits' => 10,
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
		];
	}

	private function getLevelingUpManager(
		?UserOptionsLookup $userOptionsLookup = null,
		?ConfigurationLoader $configurationLoader = null,
		?UserImpactLookup $userImpactLookup = null,
		?TaskSuggesterFactory $taskSuggesterFactory = null,
		?NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup = null,
		?ServiceOptions $serviceOptions = null,
		?Config $growthConfig = null
	): LevelingUpManager {
		$defaultConfigValues = $this->getDefaultConfigValues();
		$serviceOptions ??= new ServiceOptions(
			LevelingUpManager::CONSTRUCTOR_OPTIONS,
			new HashConfig( $defaultConfigValues )
		);

		$growthConfig ??= new HashConfig( $defaultConfigValues );
		return new LevelingUpManager(
			$serviceOptions,
			$this->createNoOpAbstractMock( IConnectionProvider::class ),
			$this->createNoOpAbstractMock( NameTableStore::class ),
			$userOptionsLookup ?? $this->getUserOptionsLookup(),
			$this->createNoOpAbstractMock( UserFactory::class ),
			$this->createNoOpAbstractMock( UserEditTracker::class ),
			$configurationLoader ?? $this->getConfigurationLoader(),
			$userImpactLookup ?? $this->getUserImpactLookup(),
			$taskSuggesterFactory ?? $this->getTaskSuggesterFactory(),
			$newcomerTasksUserOptionsLookup ?? $this->getNewcomerTasksUserOptionsLookup(),
			new NullLogger(),
			$growthConfig
		);
	}

	private function getUserImpactLookup(): UserImpactLookup {
		return $this->createMock( UserImpactLookup::class );
	}

	private function getUserOptionsLookup(): UserOptionsLookup {
		return $this->createMock( UserOptionsLookup::class );
	}

	private function getConfigurationLoader(): ConfigurationLoader {
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link-recommendation', TaskType::DIFFICULTY_EASY );
		$taskType3 = new TaskType( 'update', TaskType::DIFFICULTY_MEDIUM );
		$taskType4 = new TaskType( 'cx', TaskType::DIFFICULTY_HARD );
		$taskType5 = new TaskType( 'newarticle', TaskType::DIFFICULTY_HARD );
		$taskType6 = new TaskType( 'links', TaskType::DIFFICULTY_EASY );
		return new StaticConfigurationLoader( [
			$taskType2, $taskType4, $taskType5, $taskType1, $taskType3, $taskType6
		] );
	}

	private function getTaskSuggesterFactory( ?TaskSuggester $taskSuggester = null ): TaskSuggesterFactory {
		$taskSuggesterFactory = $this->createMock( TaskSuggesterFactory::class );
		$taskSuggesterFactory->method( 'create' )->willReturn(
			$taskSuggester ?? $this->getTaskSuggester()
		);
		return $taskSuggesterFactory;
	}

	private function getTaskSuggester(): TaskSuggester {
		return $this->createMock( TaskSuggester::class );
	}

	private function getNewcomerTasksUserOptionsLookup(): NewcomerTasksUserOptionsLookup {
		return $this->createMock( NewcomerTasksUserOptionsLookup::class );
	}

	/**
	 * @dataProvider provideShouldSendKeepGoingNotification
	 */
	public function testShouldSendKeepGoingNotification(
		int $editCount,
		int $maxThreshold,
		bool $expected
	) {
		$minThreshold = TestingAccessWrapper::constant(
			LevelingUpManager::class,
			'KEEP_GOING_NOTIFICATION_THRESHOLD_MINIMUM'
		);

		$userIdentity = new UserIdentityValue( 1, 'TestUser' );

		$userImpact = $this->createMock( UserImpact::class );
		$userImpact->method( 'getNewcomerTaskEditCount' )
			->willReturn( $editCount );

		$userImpactLookup = $this->createMock( UserImpactLookup::class );
		$userImpactLookup->method( 'getUserImpact' )
			->with( $userIdentity )
			->willReturn( $userImpact );

		$growthConfig = new HashConfig( [
			'GELevelingUpKeepGoingNotificationThresholdsMaximum' => $maxThreshold
		] );

		$levelingUpManager = $this->getLevelingUpManager(
			null,
			null,
			$userImpactLookup,
			null,
			null,
			null,
			$growthConfig
		);

		$this->assertSame(
			$expected,
			$levelingUpManager->shouldSendKeepGoingNotification( $userIdentity ),
			"Expected shouldSendKeepGoingNotification to return " .
			( $expected ? 'true' : 'false' ) .
			" when edit count is $editCount and thresholds are min=$minThreshold, max=$maxThreshold"
		);
	}

	/**
	 * Data provider for testShouldSendKeepGoingNotification
	 *
	 * Structure: [ editCount, maxThreshold, expectedResult ]
	 *
	 * @return array
	 */
	public static function provideShouldSendKeepGoingNotification() {
		return [
			'Below minimum threshold' => [ 0, 5, false ],
			'At minimum threshold' => [ 1, 5, true ],
			'Between min and max' => [ 3, 5, true ],
			'At maximum threshold' => [ 5, 5, true ],
			'Above maximum threshold' => [ 6, 5, false ],
			'At maximum threshold when max=3' => [ 3, 3, true ],
			'Above maximum threshold when max=3' => [ 4, 3, false ],
		];
	}

}
