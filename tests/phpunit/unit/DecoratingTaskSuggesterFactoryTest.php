<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\UserSettingsDecorator;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use HashConfig;
use MediaWiki\User\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use TitleValue;
use Wikimedia\ObjectFactory;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\UserSettingsDecorator
 */
class DecoratingTaskSuggesterFactoryTest extends MediaWikiUnitTestCase {

	public function testCreate() {
		$user = new UserIdentityValue( 1, 'User', 1 );
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$tasks = [
			new Task( $taskType, new TitleValue( NS_MAIN, 'Foo' ) ),
			new Task( $taskType, new TitleValue( NS_MAIN, 'Bar' ) ),
		];
		$suggester = new StaticTaskSuggester( $tasks );
		$innerFactory = new StaticTaskSuggesterFactory( $suggester );
		$objectFactory = new ObjectFactory( $this->getEmptyContainer() );

		$factory = new DecoratingTaskSuggesterFactory( $innerFactory, $objectFactory, [] );
		$this->assertSame( $suggester, $factory->create() );

		$factory = new DecoratingTaskSuggesterFactory( $innerFactory, $objectFactory, [
			[
				'class' => UserSettingsDecorator::class,
				'args' => [
					new StaticUserOptionsLookup( [] ),
					new HashConfig( [ 'GENewcomerTasksTopicType' => 'x' ] ),
				],
			],
		] );
		$this->assertInstanceOf( UserSettingsDecorator::class, $factory->create() );
		$this->assertArrayEquals( $tasks, iterator_to_array( $factory->create()->suggest( $user ) ) );
	}

	private function getEmptyContainer() {
		return new class implements ContainerInterface {
			public function get( $id ) {
				throw new class implements ContainerExceptionInterface {
				};
			}

			public function has( $id ) {
				return false;
			}
		};
	}

}
