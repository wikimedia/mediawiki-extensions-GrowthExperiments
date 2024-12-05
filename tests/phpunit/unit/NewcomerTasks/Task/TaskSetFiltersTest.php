<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;

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

}
