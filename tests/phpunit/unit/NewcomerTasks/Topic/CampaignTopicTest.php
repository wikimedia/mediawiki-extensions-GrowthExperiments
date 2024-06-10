<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\CampaignTopic
 */
class CampaignTopicTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$topic = new CampaignTopic( 'biology', 'science', 'hastemplate:Taxobox' );
		$topic2 = $codec->unserialize( $codec->serialize( $topic ) );
		$this->assertEquals( $topic, $topic2 );
	}

}
