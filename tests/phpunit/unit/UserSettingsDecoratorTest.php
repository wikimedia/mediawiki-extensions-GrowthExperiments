<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\UserSettingsDecorator;
use HashConfig;
use MediaWiki\User\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\TaskSuggester\UserSettingsDecorator
 */
class UserSettingsDecoratorTest extends TestCase {

	/**
	 * @covers ::suggest
	 */
	public function testSuggest() {
		$user1 = new UserIdentityValue( 1, 'User1', 1 );
		$user2 = new UserIdentityValue( 2, 'User2', 2 );
		$user3 = new UserIdentityValue( 3, 'User3', 3 );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "tasktypes" ]',
				SuggestedEdits::TOPICS_PREF => '[ "topics" ]',
				SuggestedEdits::TOPICS_ORES_PREF => '[ "ores" ]',
			],
			'User2' => [
				SuggestedEdits::TASKTYPES_PREF => '123',
				SuggestedEdits::TOPICS_PREF => '()%=',
				SuggestedEdits::TOPICS_ORES_PREF => 'true',
			],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksTopicType' => PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
		] );
		$taskSet = new TaskSet( [], 0, 0 );

		$suggester = $this->getMockTaskSuggester();
		$suggester->expects( $this->once() )->method( 'suggest' )
			->with( $user1, [ 'filter1' ], [ 'filter2' ], 10, 5, true )
			->willReturn( $taskSet );
		$decorator = new UserSettingsDecorator( $suggester, $userOptionsLookup, $config );
		$this->assertSame( $taskSet, $decorator->suggest(
			$user1, [ 'filter1' ], [ 'filter2' ], 10, 5, true ) );

		$suggester = $this->getMockTaskSuggester();
		$suggester->expects( $this->once() )->method( 'suggest' )
			->with( $user1, [], [], 10, 5, true )
			->willReturn( $taskSet );
		$decorator = new UserSettingsDecorator( $suggester, $userOptionsLookup, $config );
		$this->assertSame( $taskSet, $decorator->suggest(
			$user1, [], [], 10, 5, true ) );

		$suggester = $this->getMockTaskSuggester();
		$suggester->expects( $this->once() )->method( 'suggest' )
			->with( $user1, [ 'tasktypes' ], [ 'ores' ], 10, 5, true )
			->willReturn( $taskSet );
		$decorator = new UserSettingsDecorator( $suggester, $userOptionsLookup, $config );
		$this->assertSame( $taskSet, $decorator->suggest(
			$user1, null, null, 10, 5, true ) );

		$suggester = $this->getMockTaskSuggester();
		$suggester->expects( $this->once() )->method( 'suggest' )
			->with( $user1, [ 'tasktypes' ], [ 'topics' ], 10, 5, true )
			->willReturn( $taskSet );
		$config->set( 'GENewcomerTasksTopicType', PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		$decorator = new UserSettingsDecorator( $suggester, $userOptionsLookup, $config );
		$this->assertSame( $taskSet, $decorator->suggest(
			$user1, null, null, 10, 5, true ) );

		$suggester = $this->getMockTaskSuggester();
		$suggester->expects( $this->once() )->method( 'suggest' )
			->with( $user2, [], [], 10, 5, true )
			->willReturn( $taskSet );
		$decorator = new UserSettingsDecorator( $suggester, $userOptionsLookup, $config );
		$this->assertSame( $taskSet, $decorator->suggest(
			$user2, null, null, 10, 5, true ) );

		$suggester = $this->getMockTaskSuggester();
		$suggester->expects( $this->once() )->method( 'suggest' )
			->with( $user3, [], [], 10, 5, true )
			->willReturn( $taskSet );
		$decorator = new UserSettingsDecorator( $suggester, $userOptionsLookup, $config );
		$this->assertSame( $taskSet, $decorator->suggest(
			$user3, null, null, 10, 5, true ) );
	}

	/**
	 * @return TaskSuggester|MockObject
	 */
	private function getMockTaskSuggester() {
		return $this->getMockBuilder( TaskSuggester::class )
			->getMockForAbstractClass();
	}

}
