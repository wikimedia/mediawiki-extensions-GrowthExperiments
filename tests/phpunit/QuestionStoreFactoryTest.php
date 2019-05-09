<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel\QuestionStore;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use MediaWikiTestCase;
use RequestContext;

class QuestionStoreFactoryTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionStoreFactory::newFromContextAndStorage
	 */
	public function testConstructionFromUserAndStorage() {
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			RequestContext::getMain(),
			'foo'
		);
		$this->assertInstanceOf( QuestionStore::class, $questionStore );
	}
}
