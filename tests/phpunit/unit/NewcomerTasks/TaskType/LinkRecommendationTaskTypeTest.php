<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType
 */
class LinkRecommendationTaskTypeTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();

		$taskType = new LinkRecommendationTaskType(
			'foo',
			TaskType::DIFFICULTY_MEDIUM,
			[ 'setting' => 'value' ],
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
		$taskType2 = $codec->deserialize( $codec->serialize( $taskType ) );
		$this->assertEquals( $taskType, $taskType2 );
	}

}
