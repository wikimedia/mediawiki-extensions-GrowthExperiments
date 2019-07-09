<?php


namespace GrowthExperiments\Tests;

use DerivativeContext;
use FauxRequest;
use FormatJson;
use GrowthExperiments\HelpPanel\HelpModuleQuestionPoster;
use GrowthExperiments\HelpPanel\HelpPanelQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use GrowthExperiments\HomepageModules\Help;
use HashConfig;
use MediaWikiTestCase;
use RequestContext;
use User;

/**
 * @group medium
 * @group Database
 */
class HelpModuleQuestionPosterTest extends MediaWikiTestCase {

	/**
	 * @var User|null
	 */
	private $mutableTestUser = null;

	/**
	 * @throws \MWException
	 * @expectedExceptionMessage User must be logged-in.
	 * @expectedException \MWException
	 * @covers \GrowthExperiments\HelpPanel\HelpModuleQuestionPoster::__construct
	 */
	public function testConstruct() {
		$context = $this->buildContext();
		$context->getUser()->logout();
		( new HelpModuleQuestionPoster( $context, 'foo' ) );
	}

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::submit
	 */
	public function testPostedOnTimestampSet() {
		$this->insertPage( 'HelpDeskTest', '' );
		$questionPoster = new HelpModuleQuestionPoster(
			$this->buildContext(),
			'a great question',
			''
		);
		$questionPoster->submit();
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$this->buildContext(),
			Help::QUESTION_PREF
		);
		$questions = $questionStore->loadQuestions();
		$this->assertNotEmpty( $questions[0]->getTimestamp() );
	}

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\HelpPanel\HelpPanelQuestionPoster::getNumberedSectionHeaderIfDuplicatesExist
	 */
	public function testUniqueNumbering() {
		$this->insertPage( 'HelpDeskTest', '' );
		$questionPoster = new HelpPanelQuestionPoster( $this->buildContext(), 'a great question',
			'' );
		$questionPoster->submit();
		$firstQuestion = $questionPoster->getResultUrl();
		$questionPoster = new HelpPanelQuestionPoster(
			$this->buildContext(), 'I forgot to ask', ''
		);
		$questionPoster->submit();
		$secondQuestion = $questionPoster->getResultUrl();
		$questionPoster = new HelpPanelQuestionPoster( $this->buildContext(), 'one more thing..
		.', '' );
		$questionPoster->submit();
		$thirdQuestion = $questionPoster->getResultUrl();
		$this->assertNotEquals( $firstQuestion, $secondQuestion );
		$this->assertNotEquals( $secondQuestion, $thirdQuestion );
	}

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\HelpPanel\HelpPanelQuestionPoster::getNumberedSectionHeaderIfDuplicatesExist
	 * @group Broken
	 */
	public function testNumberingIfQuestionIsArchived() {
		$this->insertPage( 'HelpDeskTest', '' );
		$questionPoster = new HelpModuleQuestionPoster(
			$this->buildContext(),
			'a great question',
			''
		);
		$questionPoster->submit();
		$questionPoster = new HelpModuleQuestionPoster(
			$this->buildContext(),
			'I forgot to ask',
			''
		);
		$questionPoster->submit();
		$questionPoster = new HelpModuleQuestionPoster(
			$this->buildContext(),
			'one more thing...',
			''
		);
		$questionPoster->submit();
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$this->buildContext(),
			Help::QUESTION_PREF
		);
		$questions = $questionStore->loadQuestions();
		$this->assertEquals(
			'(2)', substr( $questions[1]->getSectionHeader(), -3 )
		);
		$this->assertEquals(
			'(3)', substr( $questions[0]->getSectionHeader(), -3 )
		);
		unset( $questions[1] );
		$updateUser = $this->mutableTestUser->getInstanceForUpdate();
		$updateUser->setOption(
			Help::QUESTION_PREF,
			FormatJson::encode( $questions )
		);
		$updateUser->saveSettings();
		$questionPoster = new HelpModuleQuestionPoster(
			$this->buildContext(),
			'why was I reverted?',
			''
		);
		$questionPoster->submit();
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$this->buildContext(),
			Help::QUESTION_PREF
		);
		$questions = $questionStore->loadQuestions();
		$this->assertEquals(
			'(2)', substr( $questions[0]->getSectionHeader(), -3 )
		);
	}

	private function buildContext( $helpDeskTitle = 'HelpDeskTest' ) {
		if ( $this->mutableTestUser === null ) {
			$this->mutableTestUser = $this->getMutableTestUser()->getUser();
		}
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( new FauxRequest( [], true ) );
		$context->setUser( $this->mutableTestUser->getInstanceForUpdate() );
		$context->setConfig( new HashConfig( [
			'GEHelpPanelHelpDeskTitle' => $helpDeskTitle,
		] ) );
		return $context;
	}

}
