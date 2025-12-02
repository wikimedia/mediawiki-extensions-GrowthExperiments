<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionRecord;
use GrowthExperiments\HelpPanel\QuestionStore;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group medium
 */
class QuestionStoreTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionStore::__construct
	 */
	public function testValidConstructionDoesntCauseErrors() {
		$services = $this->getServiceContainer();
		$questionStore = new QuestionStore(
			$this->getTestSysop()->getUser(),
			'foo',
			$services->getRevisionStore(),
			$services->getContentLanguage(),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup(),
			$services->getJobQueueGroup(),
			RequestContext::getMain()->getRequest()->wasPosted()
		);
		$this->assertInstanceOf( QuestionStore::class, $questionStore );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionStore::add
	 */
	public function testQuestionAdd() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$user = $this->getMutableTestUser()->getUser();
		$context->setRequest( new FauxRequest( [], true ) );
		$context->setUser( $user );
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$context,
			HelpdeskQuestionPoster::QUESTION_PREF
		);
		$timestamp = (int)wfTimestamp();
		$question = new QuestionRecord(
			'foo',
			'bar',
			123,
			$timestamp,
			'https://mediawiki.org',
			CONTENT_MODEL_WIKITEXT
		);
		$questionStore->add( $question );
		$context->setUser( $user );
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$context,
			HelpdeskQuestionPoster::QUESTION_PREF
		);
		$loadedQuestions = $questionStore->loadQuestions();
		$loadedQuestion = current( $loadedQuestions );
		$this->assertArrayEquals(
			[
				'questionText' => 'foo',
				'sectionHeader' => 'bar',
				'revId' => 123,
				'resultUrl' => 'https://mediawiki.org',
				'archiveUrl' => '',
				'timestamp' => $timestamp,
				'isArchived' => false,
				'isVisible' => true,
				'contentModel' => CONTENT_MODEL_WIKITEXT,
			],
			$loadedQuestion->jsonSerialize(),
			false,
			true
		);
	}

}
