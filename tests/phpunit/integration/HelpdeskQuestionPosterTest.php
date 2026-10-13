<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group medium
 * @group Database
 */
class HelpdeskQuestionPosterTest extends MediaWikiIntegrationTestCase {
	use CommunityConfigurationTestHelpers;

	/**
	 * @var User|null
	 */
	private $mutableTestUser = null;

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster::__construct
	 */
	public function testConstruct() {
		$this->overrideProviderConfig( [
			'GEHelpPanelHelpDeskTitle' => 'HelpDeskTest',
		], 'HelpPanel' );
		$context = $this->buildContext();
		$context->setUser( new User() );

		$this->expectException( UserNotLoggedIn::class );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getRedirectLookup(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getStatsFactory(),
			$geServices->getHelpPanel(),
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
		$title = Title::makeTitle( self::getDefaultWikitextNS(), 'HelpDeskTest' );
		$this->overrideProviderConfig( [
			'GEHelpPanelHelpDeskTitle' => $title->getPrefixedDBkey(),
		], 'HelpPanel' );
		$this->insertPage( $title, '' );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getRedirectLookup(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getStatsFactory(),
			$geServices->getHelpPanel(),
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$this->buildContext(),
			'a great question'
		);
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$this->assertStringNotContainsString( '##', $questionPoster->getResultUrl() );
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $title );
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
		$title = Title::makeTitle(
			self::getDefaultWikitextNS(),
			'TestSubmitNewTarget'
		);
		$this->overrideProviderConfig( [
			'GEHelpPanelHelpDeskTitle' => $title->getPrefixedDBkey(),
		], 'HelpPanel' );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getRedirectLookup(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getStatsFactory(),
			$geServices->getHelpPanel(),
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$this->buildContext(),
			'a great question'
		);
		$questionPoster->submit();
		$revision = $questionPoster->getRevisionId();
		$this->assertGreaterThan( 0, $revision );
		$this->assertStringNotContainsString( '##', $questionPoster->getResultUrl() );
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
		$namespace = self::getDefaultWikitextNS();
		$namespaceName = $this->getServiceContainer()->getNamespaceInfo()->getCanonicalName( $namespace );
		$this->overrideProviderConfig( [
			'GEHelpPanelHelpDeskTitle' => $namespaceName . ':sample',
		], 'HelpPanel' );
		$title = Title::newFromText( 'sample', $namespace );
		$this->insertPage( $title );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getRedirectLookup(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getStatsFactory(),
			$geServices->getHelpPanel(),
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$this->buildContext(),
			'blah',
			'sample'
		);
		$this->assertStatusOK( $questionPoster->validateRelevantTitle() );
		$questionPoster = new HelpdeskQuestionPoster(
			$this->getServiceContainer()->getWikiPageFactory(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getRedirectLookup(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getStatsFactory(),
			$geServices->getHelpPanel(),
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
			$this->buildContext(),
			'blah',
			'>123'
		);
		$this->assertStatusError(
			'growthexperiments-help-panel-questionposter-invalid-title',
			$questionPoster->validateRelevantTitle()
		);
	}

	private function buildContext() {
		if ( $this->mutableTestUser === null ) {
			$this->mutableTestUser = $this->getMutableTestUser()->getUser();
		}
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( new FauxRequest( [], true ) );
		$context->setUser( $this->mutableTestUser );
		return $context;
	}

}
