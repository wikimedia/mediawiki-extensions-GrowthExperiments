<?php

namespace GrowthExperiments\Specials;

use ConfigException;
use DeferredUpdates;
use Exception;
use ExtensionRegistry;
use GrowthExperiments\EventLogging\SpecialHomepageLogger;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\Tutorial;
use Html;
use MediaWiki\Logger\LoggerFactory;
use GrowthExperiments\HomepageModules\Start;
use MediaWiki\Session\SessionManager;
use SpecialPage;
use Title;
use UserNotLoggedIn;

class SpecialHomepage extends SpecialPage {

	/**
	 * @var string Unique identifier for this specific rendering of Special:Homepage.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private $pageviewToken;

	public function __construct() {
		parent::__construct( 'Homepage', '', false );
		$this->pageviewToken = $this->generateUniqueToken();
	}

	private function handleTutorialVisit( $par ) {
		$tutorialTitle = Title::newFromText(
			$this->getConfig()->get( Tutorial::TUTORIAL_TITLE_CONFIG )
		);
		if ( $tutorialTitle->getPrefixedDBkey() !== $par ) {
			return;
		}
		$user = $this->getUser();
		if ( $this->getRequest()->wasPosted() &&
			 $user->isLoggedIn() &&
			 !$user->getBoolOption( Tutorial::TUTORIAL_PREF ) ) {
			DeferredUpdates::addCallableUpdate( function () use ( $user ) {
				$user->setOption( Tutorial::TUTORIAL_PREF, 1 );
				$user->saveSettings();
			} );
		}
		$this->getOutput()->redirect( $tutorialTitle->getLinkURL() );
	}

	/**
	 * @inheritDoc
	 * @throws ConfigException
	 * @throws UserNotLoggedIn
	 */
	public function execute( $par = '' ) {
		$out = $this->getContext()->getOutput();
		$this->requireLogin();
		parent::execute( $par );
		$this->handleTutorialVisit( $par );

		$out->setSubtitle( $this->getSubtitle() );
		$loggingEnabled = $this->getConfig()->get( 'GEHomepageLoggingEnabled' );
		$out->addJsConfigVars( [
			'wgGEHomepagePageviewToken' => $this->pageviewToken,
			'wgGEHomepageLoggingEnabled' => $loggingEnabled
		] );
		$out->addModules( 'ext.growthExperiments.Homepage' );
		$out->enableOOUI();
		$out->addModuleStyles( 'ext.growthExperiments.Homepage.styles' );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'growthexperiments-homepage-container'
		] ) );
		$modules = $this->getModules();
		$renderedModules = [];
		foreach ( $this->getModuleGroups() as $group => $moduleNames ) {
			$out->addHTML( Html::openElement( 'div', [
				'class' => "growthexperiments-homepage-group-$group",
			] ) );
			foreach ( $moduleNames as $moduleName ) {
				/** @var HomepageModule $module */
				$module = $modules[$moduleName];
				try {
					$out->addHTML( $module->render() );
					$renderedModules[$moduleName] = $module;
				} catch ( Exception $e ) {
					LoggerFactory::getInstance( 'GrowthExperiments' )->error(
						"Homepage module '{class}' cannot be rendered.",
						[
							'class' => get_class( $module ),
							'msg' => $e->getMessage(),
							'trace' => $e->getTraceAsString(),
						]
					);
				}
			}
			$out->addHTML( Html::closeElement( 'div' ) );
		}
		$out->addHTML( Html::closeElement( 'div' ) );

		if ( $loggingEnabled &&
			 ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) &&
			 count( $renderedModules ) ) {
			$logger = new SpecialHomepageLogger(
				$this->pageviewToken,
				$this->getContext()->getUser(),
				$this->getRequest(),
				$out->getSkin()->getSkinName() === 'minerva',
				$renderedModules
			);
			DeferredUpdates::addCallableUpdate( function () use ( $logger ) {
				$logger->log();
			} );
		}
	}

	/**
	 * Overridden in order to inject the current user's name as message parameter
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-homepage-specialpage-title' )
			->params( $this->getUser()->getName() )
			->text();
	}

	/**
	 * @return HomepageModule[]
	 */
	private function getModules() {
		return [
			'start' => new Start( $this->getContext() ),
			'impact' => new Impact( $this->getContext() ),
			'help' => new Help( $this->getContext() ),
			'mentorship' => new Mentorship( $this->getContext() ),
		];
	}

	private function getModuleGroups() {
		return [
			'main' => [ 'start', 'impact', 'mentorship' ],
			'sidebar' => [ 'help' ]
		];
	}

	private function getSubtitle() {
		return $this->msg( 'growthexperiments-homepage-specialpage-subtitle' )
				->params( $this->getUser()->getName() );
	}

	/**
	 * @return string
	 */
	private function generateUniqueToken() {
		// Can't use SessionManager::singleton() here because while it returns an
		// instance of SessionManager, the code comment says it returns SessionManagerInterface
		// and that doesn't have generateSessionId(). So the code works but phan rejects it.
		$sessionManager = new SessionManager();
		return $sessionManager->generateSessionId();
	}
}
