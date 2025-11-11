<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;
use MessageLocalizer;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\Topic
 */
class TopicTest extends MediaWikiUnitTestCase {

	public function testTopic() {
		$fakeContext = $this->getMockBuilder( MessageLocalizer::class )
			->onlyMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$fakeContext->method( 'msg' )->willReturnCallback(
			fn ( $k, ...$p ) => $this->getMockMessage( $k, $p )
		);

		$topic = new Topic( 'foo' );
		$this->assertSame( 'foo', $topic->getId() );
		$this->assertNull( $topic->getGroupId() );
		$this->assertSame( 'growthexperiments-homepage-suggestededits-topic-name-foo',
			$topic->getName( $fakeContext )->text() );
		$this->assertSame( [
			'id' => 'foo',
			'name' => 'growthexperiments-homepage-suggestededits-topic-name-foo',
			'groupId' => null,
			'groupName' => null,
		], $topic->getViewData( $fakeContext ) );

		$topic = new Topic( 'foo', 'bar' );
		$this->assertSame( 'bar', $topic->getGroupId() );
		$this->assertSame( 'growthexperiments-homepage-suggestededits-topic-group-name-bar',
			$topic->getGroupName( $fakeContext )->text() );
		$this->assertSame( [
			'id' => 'foo',
			'name' => 'growthexperiments-homepage-suggestededits-topic-name-foo',
			'groupId' => 'bar',
			'groupName' => 'growthexperiments-homepage-suggestededits-topic-group-name-bar',
		], $topic->getViewData( $fakeContext ) );
	}

	public function testJsonSerialization() {
		// JsonCodec isn't stable to construct but there is not better way in a unit test.
		$codec = new JsonCodec();
		$topic = new Topic( 'foo', 'bar' );
		$topic2 = $codec->deserialize( $codec->serialize( $topic ) );
		$this->assertEquals( $topic, $topic2 );
	}

}
