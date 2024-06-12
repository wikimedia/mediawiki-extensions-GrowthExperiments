<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HelpPanel\QuestionStore;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use MediaWikiIntegrationTestCase;
use RequestContext;

class QuestionStoreFactoryTest extends MediaWikiIntegrationTestCase {

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
