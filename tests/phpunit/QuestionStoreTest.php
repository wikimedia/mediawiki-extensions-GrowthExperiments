<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel\QuestionStore;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;

/**
 * Class QuestionStoreTest
 * @group Database
 * @group medium
 */
class QuestionStoreTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionStore::__construct
	 */
	public function testValidConstructionDoesntCauseErrors() {
		$questionStore = new QuestionStore(
			$this->getTestSysop()->getUser(),
			'foo',
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			MediaWikiServices::getInstance()->getContentLanguage()
		);
		$this->assertInstanceOf( QuestionStore::class, $questionStore );
	}

}
