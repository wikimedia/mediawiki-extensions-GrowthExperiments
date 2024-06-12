<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\TaskType\NullTaskType;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\NullTaskType
 */
class NullTaskTypeTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$taskType = new NullTaskType( 'foo', 'bar' );
		$taskType2 = $codec->unserialize( $codec->serialize( $taskType ) );
		$this->assertEquals( $taskType, $taskType2 );
	}

}
