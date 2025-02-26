<?php

namespace GrowthExperiments\Tests\Unit\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoaderTrait;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoaderTrait
 */
class ConfigurationLoaderTraitTest extends MediaWikiUnitTestCase {

	/**
	 * @param mixed $taskTypesResult The value to return from loadTaskTypes()
	 * @param mixed $topicsResult The value to return from loadTopics()
	 * @return mixed Instance of anonymous class using ConfigurationLoaderTrait
	 * @phan-return object
	 */
	private function createTraitImplementation( $taskTypesResult, $topicsResult ) {
		// @phpcs:ignore MediaWiki.Commenting.FunctionComment.ObjectTypeHintReturn
		return new class( $taskTypesResult, $topicsResult ) {
			use ConfigurationLoaderTrait;

			private $taskTypesResult;
			private $topicsResult;

			public function __construct( $taskTypesResult, $topicsResult ) {
				$this->taskTypesResult = $taskTypesResult;
				$this->topicsResult = $topicsResult;
			}

			public function loadTaskTypes() {
				return $this->taskTypesResult;
			}

			public function loadTopics() {
				return $this->topicsResult;
			}
		};
	}

	public function testGetTaskTypes(): void {
		$taskType1 = $this->createMock( TaskType::class );
		$taskType1->method( 'getId' )->willReturn( 'copyedit' );

		$taskType2 = $this->createMock( TaskType::class );
		$taskType2->method( 'getId' )->willReturn( 'references' );

		$taskTypes = [ $taskType1, $taskType2 ];
		$traitImpl = $this->createTraitImplementation( $taskTypes, [] );

		$result = $traitImpl->getTaskTypes();

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'copyedit', $result );
		$this->assertArrayHasKey( 'references', $result );
		$this->assertSame( $taskType1, $result['copyedit'] );
		$this->assertSame( $taskType2, $result['references'] );

		$errorStatus = StatusValue::newFatal( 'some-error' );
		$traitImpl = $this->createTraitImplementation( $errorStatus, [] );

		$result = $traitImpl->getTaskTypes();

		$this->assertIsArray( $result );
		$this->assertSame( [], $result, 'Should return an empty array when StatusValue error is returned' );
	}

	public function testGetTopics(): void {
		$topic1 = $this->createMock( Topic::class );
		$topic1->method( 'getId' )->willReturn( 'art' );

		$topic2 = $this->createMock( Topic::class );
		$topic2->method( 'getId' )->willReturn( 'science' );

		$topics = [ $topic1, $topic2 ];
		$traitImpl = $this->createTraitImplementation( [], $topics );

		$result = $traitImpl->getTopics();

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'art', $result );
		$this->assertArrayHasKey( 'science', $result );
		$this->assertSame( $topic1, $result['art'] );
		$this->assertSame( $topic2, $result['science'] );

		$errorStatus = StatusValue::newFatal( 'some-error' );
		$traitImpl = $this->createTraitImplementation( [], $errorStatus );

		$result = $traitImpl->getTopics();

		$this->assertIsArray( $result );
		$this->assertSame( [], $result, 'Should return an empty array when StatusValue error is returned' );
	}
}
