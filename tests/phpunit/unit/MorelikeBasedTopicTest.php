<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use MediaWiki\Linker\LinkTarget;
use MediaWikiUnitTestCase;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic
 */
class MorelikeBasedTopicTest extends MediaWikiUnitTestCase {

	public function testGetReferencePages() {
		$topic = new MorelikeBasedTopic( 'foo', [
			new TitleValue( NS_MAIN, 'Title1' ),
			new TitleValue( NS_MAIN, 'Title2' ),
		] );
		$this->assertEquals( [ 'Title1', 'Title2' ], array_map( function ( LinkTarget $title ) {
			return $title->getDBkey();
		}, $topic->getReferencePages() ) );
	}

}
