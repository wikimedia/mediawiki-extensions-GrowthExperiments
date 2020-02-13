<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic
 */
class OresBasedTopicTest extends MediaWikiUnitTestCase {

	public function testGetOresTopics() {
		$topic = new OresBasedTopic( 'foo', 'bar', [ 'x', 'y', 'z' ] );
		$this->assertSame( [ 'x', 'y', 'z' ], $topic->getOresTopics() );
	}

}
