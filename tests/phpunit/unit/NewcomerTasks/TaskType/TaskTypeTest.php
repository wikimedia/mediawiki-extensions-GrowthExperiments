<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TaskType
 */
class TaskTypeTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$taskType = new TaskType(
			'foo',
			TaskType::DIFFICULTY_MEDIUM,
			[ 'extra' => 'data' ],
			[
				new TitleValue( NS_TEMPLATE, 'Foo' ),
				new TitleValue( NS_TEMPLATE, 'Bar' ),
			],
			[
				new TitleValue( NS_CATEGORY, 'Foo' ),
				new TitleValue( NS_CATEGORY, 'Bar' ),
			]
		);
		$taskType2 = $codec->unserialize( $codec->serialize( $taskType ) );
		$this->assertEquals( $taskType, $taskType2 );
	}

}
