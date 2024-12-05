<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic
 */
class OresBasedTopicTest extends MediaWikiUnitTestCase {

	public function testGetOresTopics() {
		$topic = new OresBasedTopic( 'foo', 'bar', [ 'x', 'y', 'z' ] );
		$this->assertSame( [ 'x', 'y', 'z' ], $topic->getOresTopics() );
	}

	public function testJsonSerialization() {
		// JsonCodec isn't stable to construct but there is not better way in a unit test.
		$codec = new JsonCodec();
		$topic = new OresBasedTopic( 'foo', 'bar', [ 'x', 'y', 'z' ] );
		$topic2 = $codec->deserialize( $codec->serialize( $topic ) );
		$this->assertEquals( $topic, $topic2 );
	}

}
