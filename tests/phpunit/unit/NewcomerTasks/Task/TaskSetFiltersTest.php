<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Task\TaskSetFilters
 */
class TaskSetFiltersTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$taskSetFilters = new TaskSetFilters( [ 'x', 'y' ], [ 'z' ] );
		$taskSetFilters2 = $codec->deserialize( $codec->serialize( $taskSetFilters ) );
		$this->assertEquals( $taskSetFilters, $taskSetFilters2 );
	}

	public function testJsonSerializationWithInterests() {
		$codec = new JsonCodec();
		$taskSetFilters = new TaskSetFilters( [ 'x', 'y' ], [], null, [ 'Albert Einstein' ] );
		$taskSetFilters2 = $codec->deserialize( $codec->serialize( $taskSetFilters ) );
		$this->assertEquals( $taskSetFilters, $taskSetFilters2 );
		$this->assertSame( [ 'Albert Einstein' ], $taskSetFilters2->getInterestFilters() );
	}

	public function testSerializationWithoutInterestsIsUnchanged() {
		$taskSetFilters = new TaskSetFilters( [ 'x', 'y' ], [ 'z' ] );
		$this->assertSame( [
			'task' => [ 'x', 'y' ],
			'topic' => [ 'z' ],
			'topicMode' => 'OR',
		], $taskSetFilters->toJsonArray() );
	}

	public function testEqualityViaJsonArray() {
		$interestFilters = new TaskSetFilters( [ 'x' ], [], null, [ 'Coffee' ] );
		$sameInterestFilters = new TaskSetFilters( [ 'x' ], [], null, [ 'Coffee' ] );
		$otherInterestFilters = new TaskSetFilters( [ 'x' ], [], null, [ 'Tea' ] );
		$topicFilters = new TaskSetFilters( [ 'x' ], [ 'Coffee' ] );

		$this->assertSame( $interestFilters->toJsonArray(), $sameInterestFilters->toJsonArray() );
		$this->assertNotSame( $interestFilters->toJsonArray(), $otherInterestFilters->toJsonArray() );
		$this->assertNotSame( $interestFilters->toJsonArray(), $topicFilters->toJsonArray() );
	}

	public function testRejectsTopicsCombinedWithInterests() {
		$this->expectException( ParameterAssertionException::class );
		new TaskSetFilters( [ 'x' ], [ 'z' ], null, [ 'Coffee' ] );
	}

}
