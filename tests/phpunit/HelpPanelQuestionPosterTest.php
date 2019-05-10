<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use FauxRequest;
use GrowthExperiments\HelpPanel\HelpPanelQuestionPoster;
use HashConfig;
use MediaWikiTestCase;
use RequestContext;
use Status;
use Title;
use User;
use WikiPage;

/**
 * Class HelpPanelQuestionPosterTest
 *
 * @group medium
 * @group Database
 */
class HelpPanelQuestionPosterTest extends MediaWikiTestCase {

	/**
	 * @var User|null
	 */
	private $mutableTestUser = null;

	/**
	 * @throws \MWException
	 * @expectedExceptionMessage User must be logged-in.
	 * @expectedException \MWException
	 * @covers \GrowthExperiments\HelpPanel\HelpPanelQuestionPoster::__construct
	 */
	public function testConstruct() {
		$context = $this->buildContext();
		$context->getUser()->logout();
		( new HelpPanelQuestionPoster( $context, 'foo' ) );
	}

	/**
	 * @throws \MWException
	 * @group Database
	 * @covers \GrowthExperiments\HelpPanel\HelpPanelQuestionPoster::submit
	 */
	public function testSubmitExistingTarget() {
		$this->insertPage( 'HelpDeskTest', '' );
		$questionPoster = new HelpPanelQuestionPoster( $this->buildContext(), 'a great question' );
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
	 * @covers \GrowthExperiments\HelpPanel\HelpPanelQuestionPoster::submit
	 */
	public function testSubmitNewTarget() {
		$title = $this->getNonexistingTestPage()->getTitle();
		$questionPoster = new HelpPanelQuestionPoster(
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
	 * @covers \GrowthExperiments\HelpPanel\HelpPanelQuestionPoster::validateRelevantTitle
	 * @throws \MWException
	 */
	public function testValidateRelevantTitle() {
		$this->insertPage( 'sample' );
		$questionPoster = new HelpPanelQuestionPoster( $this->buildContext(), 'blah', 'sample' );
		$this->assertEquals(
			Status::newGood(),
			$questionPoster->validateRelevantTitle()
		);
		$questionPoster = new HelpPanelQuestionPoster( $this->buildContext(), 'blah', '>123' );
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
