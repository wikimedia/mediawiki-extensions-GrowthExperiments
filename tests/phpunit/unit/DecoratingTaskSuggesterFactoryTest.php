<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\TaskSetListener;
use GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Status\StatusFormatter;
use MediaWikiUnitTestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\ObjectFactory\ObjectFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator
 */
class DecoratingTaskSuggesterFactoryTest extends MediaWikiUnitTestCase {
	public function testCreate() {
		$innerSuggester = new StaticTaskSuggester( [] );
		$innerFactory = new StaticTaskSuggesterFactory(
			$innerSuggester,
			$this->createNoOpMock( StatusFormatter::class ),
			new NullLogger()
		);

		$objectFactory = new ObjectFactory( $this->getEmptyContainer() );
		$factory = new DecoratingTaskSuggesterFactory( $innerFactory, $objectFactory, [], new NullLogger() );
		$this->assertSame( $innerSuggester, $factory->create() );

		$factory = new DecoratingTaskSuggesterFactory( $innerFactory, $objectFactory, [
			[
				'class' => CacheDecorator::class,
				'args' => [
					$this->createMock( JobQueueGroup::class ),
					new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
					$this->createMock( TaskSetListener::class ),
					new JsonCodec(),
				],
			],
		], new NullLogger() );
		$suggester = $factory->create();
		$this->assertInstanceOf( CacheDecorator::class, $suggester );
		$wrappedSuggester = TestingAccessWrapper::newFromObject( $suggester );
		$this->assertSame( $innerSuggester, $wrappedSuggester->taskSuggester );
	}

	private function getEmptyContainer() {
		return new class implements ContainerInterface {
			public function get( string $id ) {
				throw new class implements ContainerExceptionInterface {
				};
			}

			public function has( string $id ): bool {
				return false;
			}
		};
	}
}
