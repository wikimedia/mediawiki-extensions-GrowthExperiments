<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use MessageLocalizer;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\Topic
 */
class TopicTest extends MediaWikiUnitTestCase {

	public function testTopic() {
		$fakeContext = $this->getMockBuilder( MessageLocalizer::class )
			->setMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$fakeContext->method( 'msg' )->willReturnArgument( 0 );
		/** @var MessageLocalizer $fakeContext */

		$topic = new Topic( 'foo' );
		$topic->setName( 'Some topic' );
		$this->assertSame( 'foo', $topic->getId() );
		// FIXME no way to mock while we are using the RawMessage hack so it would break the unit test
		// $this->assertSame( 'Some topic', $topic->getName( $fakeContext )->text() );
		// $this->assertSame( [ 'id' => 'foo', 'name' => 'Some topic' ],
		//	$topic->toArray( $fakeContext ) );
	}

}
