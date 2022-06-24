<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use FauxRequest;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use MediaWikiIntegrationTestCase;
use RequestContext;
use Status;
use Title;
use User;

/**
 * @group medium
 * @group Database
 */
class HelpdeskQuestionPosterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var User|null
	 */
	private $mutableTestUser = null;

	/**
	 * @throws \MWException
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::__construct
	 */
	public function testConstruct() {
		$this->setMwGlobals( 'wgGEHelpPanelHelpDeskTitle', 'HelpDeskTest' );
		$context = $this->buildContext();
		$context->setUser( new User() );

		$this->expectException( \UserNotLoggedIn::class );

		new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$context,
			'foo'
		);
	}

	/**
	 * @throws \MWException
	 * @group Database
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::submit
	 */
	public function testSubmitExistingTarget() {
		$this->setMwGlobals( 'wgGEHelpPanelHelpDeskTitle', 'HelpDeskTest' );
		$this->insertPage( 'HelpDeskTest', '' );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->buildContext(),
			'a great question'
		);
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'HelpDeskTest' ) );
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
		$this->setMwGlobals( 'wgGEHelpPanelHelpDeskTitle', $title->getPrefixedDBkey() );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->buildContext(),
			'a great question'
		);
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
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
		$this->setMwGlobals( 'wgGEHelpPanelHelpDeskTitle', 'sample' );
		$this->insertPage( 'sample' );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->buildContext(),
			'blah',
			'sample'
		);
		$this->assertEquals(
			Status::newGood(),
			$questionPoster->validateRelevantTitle()
		);
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->buildContext(),
			'blah',
			'>123'
		);
		$this->assertEquals(
			Status::newFatal( 'growthexperiments-help-panel-questionposter-invalid-title' ),
			$questionPoster->validateRelevantTitle()
		);
	}

	private function buildContext() {
		if ( $this->mutableTestUser === null ) {
			$this->mutableTestUser = $this->getMutableTestUser()->getUser();
		}
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( new FauxRequest( [], true ) );
		$context->setUser( $this->mutableTestUser->getInstanceForUpdate() );
		return $context;
	}

}
