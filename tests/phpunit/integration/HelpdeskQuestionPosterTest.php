<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use FauxRequest;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use HashConfig;
use MediaWikiTestCase;
use RequestContext;
use Status;
use Title;
use User;
use WikiPage;

/**
 * @group medium
 * @group Database
 */
class HelpdeskQuestionPosterTest extends MediaWikiTestCase {

	/**
	 * @var User|null
	 */
	private $mutableTestUser = null;

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::__construct
	 */
	public function testConstruct() {
		$context = $this->buildContext();
		$context->setUser( new User() );

		$this->expectException( \MWException::class );
		$this->expectExceptionMessage( 'User must be logged-in.' );

		( new HelpdeskQuestionPoster( $context, 'foo' ) );
	}

	/**
	 * @throws \MWException
	 * @group Database
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::submit
	 */
	public function testSubmitExistingTarget() {
		$this->insertPage( 'HelpDeskTest', '' );
		$questionPoster = new HelpdeskQuestionPoster( $this->buildContext(), 'a great question' );
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$page = new WikiPage( Title::newFromText( 'HelpDeskTest' ) );
		$this->assertRegExp(
			'/a great question/',
			$page->getContent()->getSection( 1 )->serialize()
		);
	}

	/**
	 * @throws \MWException
	 * @group Database
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::submit
	 */
	public function testSubmitNewTarget() {
		$title = $this->getNonexistingTestPage()->getTitle();
		$questionPoster = new HelpdeskQuestionPoster(
			$this->buildContext( $title->getPrefixedDBkey() ),
			'a great question'
		);
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$page = new WikiPage( $title );
		$this->assertRegExp(
			'/a great question/',
			$page->getContent()->getSection( 1 )->serialize()
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::validateRelevantTitle
	 * @throws \MWException
	 */
	public function testValidateRelevantTitle() {
		$this->insertPage( 'sample' );
		$questionPoster = new HelpdeskQuestionPoster( $this->buildContext(), 'blah', 'sample' );
		$this->assertEquals(
			Status::newGood(),
			$questionPoster->validateRelevantTitle()
		);
		$questionPoster = new HelpdeskQuestionPoster( $this->buildContext(), 'blah', '>123' );
		$this->assertEquals(
			Status::newFatal( 'growthexperiments-help-panel-questionposter-invalid-title' ),
			$questionPoster->validateRelevantTitle()
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
