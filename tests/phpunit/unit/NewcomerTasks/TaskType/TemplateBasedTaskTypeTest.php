<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType
 */
class TemplateBasedTaskTypeTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$taskType = new TemplateBasedTaskType(
			'foo',
			TaskType::DIFFICULTY_MEDIUM,
			[ 'extra' => 'data' ],
			[
				new TitleValue( NS_TEMPLATE, 'T1' ),
				new TitleValue( NS_TEMPLATE, 'T2' ),
			],
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
