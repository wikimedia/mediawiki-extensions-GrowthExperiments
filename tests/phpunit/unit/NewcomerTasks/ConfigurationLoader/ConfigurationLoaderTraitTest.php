<?php

namespace GrowthExperiments\Tests\Unit\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoaderTrait;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoaderTrait
 */
class ConfigurationLoaderTraitTest extends MediaWikiUnitTestCase {

	/**
	 * @param TaskType[]|StatusValue $taskTypesResult The value to return from loadTaskTypes()
	 * @return mixed Instance of anonymous class using ConfigurationLoaderTrait
	 * @phan-return object
	 */
	private function createTraitImplementation( $taskTypesResult ) {
		// @phpcs:ignore MediaWiki.Commenting.FunctionComment.ObjectTypeHintReturn
		return new class( $taskTypesResult ) {
			use ConfigurationLoaderTrait;

			/** @var TaskType[]|StatusValue */
			private $taskTypesResult;

			public function __construct( $taskTypesResult ) {
				$this->taskTypesResult = $taskTypesResult;
			}

			public function loadTaskTypes() {
				return $this->taskTypesResult;
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
}
