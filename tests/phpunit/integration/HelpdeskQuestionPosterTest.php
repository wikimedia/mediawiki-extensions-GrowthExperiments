<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use ExtensionRegistry;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use RequestContext;
use Status;
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
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$context,
			'foo'
		);
	}

	/**
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
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$this->buildContext(),
			'a great question'
		);
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'HelpDeskTest' ) );
		$this->assertMatchesRegularExpression(
			'/a great question/',
			$page->getContent()->getSection( 1 )->serialize()
		);
	}

	/**
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
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$this->buildContext(),
			'a great question'
		);
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->assertMatchesRegularExpression(
			'/a great question/',
			$page->getContent()->getSection( 1 )->serialize()
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::validateRelevantTitle
	 */
	public function testValidateRelevantTitle() {
		$this->setMwGlobals( 'wgGEHelpPanelHelpDeskTitle', 'sample' );
		$this->insertPage( 'sample' );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
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
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
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
