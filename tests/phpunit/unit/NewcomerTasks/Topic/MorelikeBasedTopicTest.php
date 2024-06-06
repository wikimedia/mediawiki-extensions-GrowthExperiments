<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * FIXME: Can't test get/setName because Message classes break unit tests
 *
 * @covers \GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic
 */
class MorelikeBasedTopicTest extends MediaWikiUnitTestCase {

	public function testGetReferencePages() {
		$topic = new MorelikeBasedTopic( 'foo', [
			new TitleValue( NS_MAIN, 'Title1' ),
			new TitleValue( NS_MAIN, 'Title2' ),
		] );
		$this->assertEquals( [ 'Title1', 'Title2' ], array_map( static function ( LinkTarget $title ) {
			return $title->getDBkey();
		}, $topic->getReferencePages() ) );
	}

	public function testJsonSerialization() {
		// JsonCodec isn't stable to construct but there is not better way in a unit test.
		$codec = new JsonCodec();
		$topic = new MorelikeBasedTopic( 'foo', [
			new TitleValue( NS_MAIN, 'Title1' ),
			new TitleValue( NS_MAIN, 'Title2' ),
		] );
		$topic2 = $codec->unserialize( $codec->serialize( $topic ) );
		$this->assertEquals( $topic, $topic2 );
	}

}
