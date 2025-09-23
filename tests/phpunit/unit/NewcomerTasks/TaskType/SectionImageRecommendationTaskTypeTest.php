<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType
 */
class SectionImageRecommendationTaskTypeTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$taskType = new SectionImageRecommendationTaskType(
			'some-id',
			TaskType::DIFFICULTY_MEDIUM,
			[
				'some' => 'setting',
			]
		);
		$taskType2 = $codec->deserialize( $codec->serialize( $taskType ) );
		$this->assertEquals( $taskType, $taskType2 );
	}

}
