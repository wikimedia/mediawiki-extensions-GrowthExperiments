<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel\QuestionStore;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use MediaWikiTestCase;

class QuestionStoreFactoryTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionStoreFactory::newFromUserAndStorage
	 */
	public function testConstructionFromUserAndStorage() {
		$questionStore = QuestionStoreFactory::newFromUserAndStorage(
			$this->getTestSysop()->getUser(),
			'foo'
		);
		$this->assertInstanceOf( QuestionStore::class, $questionStore );
	}
}
