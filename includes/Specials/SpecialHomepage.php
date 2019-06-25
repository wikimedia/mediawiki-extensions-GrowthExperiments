<?php

namespace GrowthExperiments\Specials;

use ConfigException;
use DeferredUpdates;
use Error;
use ErrorPageError;
use Exception;
use ExtensionRegistry;
use GrowthExperiments\EventLogging\SpecialHomepageLogger;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\BaseModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\Util;
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
		// Hack: Making the userpage the relevant title for the homepage
		// allows using the talk overlay for the talk tab on mobile.
		$this->getSkin()->setRelevantTitle( $this->getUser()->getUserPage() );
	}

	private function handleTutorialVisit( $par ) {
		$tutorialTitle = Title::newFromText(
			$this->getConfig()->get( Tutorial::TUTORIAL_TITLE_CONFIG )
		);
		if ( !$tutorialTitle || $tutorialTitle->getPrefixedDBkey() !== $par ) {
			return false;
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
		return true;
	}

	/**
	 * @inheritDoc
	 * @param string $par
	 * @throws ConfigException
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	public function execute( $par = '' ) {
		$this->requireLogin();
		parent::execute( $par );
		$this->handleDisabledPreference();
		if ( $this->handleTutorialVisit( $par ) ) {
			return;
		}

		$out = $this->getContext()->getOutput();
		$isMobile = Util::isMobile( $out->getSkin() );
		$loggingEnabled = $this->getConfig()->get( 'GEHomepageLoggingEnabled' );
		$out->addJsConfigVars( [
			'wgGEHomepagePageviewToken' => $this->pageviewToken,
			'wgGEHomepageLoggingEnabled' => $loggingEnabled
		] );
		$out->addModules( 'ext.growthExperiments.Homepage.Logging' );
		$out->enableOOUI();
		$out->addModuleStyles( 'ext.growthExperiments.Homepage.styles' );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'growthexperiments-homepage-container',
		] ) );
		$modules = $this->getModules();

		if ( $isMobile ) {
			$currentModule = $modules[$par] ?? false;
			if ( $currentModule ) {
				$renderedModules = $this->renderMobileDetails( $par, $currentModule );
			} else {
				$renderedModules = $this->renderMobileSummary();
			}
		} else {
			$renderedModules = $this->renderDesktop();
		}

		$out->addHTML( Html::closeElement( 'div' ) );

		if ( $isMobile && !$par ) {
			$this->outputDataForMobileOverlay();
		}

		if ( $loggingEnabled &&
			 ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) &&
			 count( $renderedModules ) ) {
			$logger = new SpecialHomepageLogger(
				$this->pageviewToken,
				$this->getContext()->getUser(),
				$this->getRequest(),
				$isMobile,
				$renderedModules
			);
			DeferredUpdates::addCallableUpdate( function () use ( $logger ) {
				$logger->log();
			} );
		}
	}

	/**
	 * @throws ConfigException
	 * @throws ErrorPageError
	 */
	private function handleDisabledPreference() {
		if ( !HomepageHooks::isHomepageEnabled( $this->getUser() ) ) {
			throw new ErrorPageError(
				'growthexperiments-homepage-tab',
				'growthexperiments-homepage-enable-preference'
			);
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
			'mentorship' => new Mentorship( $this->getContext() ),
			'help' => new Help( $this->getContext() ),
		];
	}

	private function getModuleGroups() {
		return [
			'main' => [ 'start', 'impact', 'mentorship' ],
			'sidebar' => [ 'help' ]
		];
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

	/**
	 * Log Exception or Error thrown when trying to render a module.
	 *
	 * Note: Some runtime errors like trying to call a function on null
	 * are reported as Exception in HHVM but Error in PHP7.
	 *
	 * try {
	 * 		$a = null;
	 * 		$a->foo();
	 * } catch ( Exception $t ) {
	 * 		echo "Exception (hhvm)";
	 * } catch ( Error $t ) {
	 * 		echo "Error (php7)";
	 * }
	 *
	 * @param HomepageModule $module
	 * @param Exception|Error $issue
	 */
	private function logModuleRenderIssue( HomepageModule $module, $issue ) {
		LoggerFactory::getInstance( 'GrowthExperiments' )->error(
			"Homepage module '{class}' cannot be rendered. {msg} {trace}",
			[
				'class' => get_class( $module ),
				'msg' => $issue->getMessage(),
				'trace' => $issue->getTraceAsString(),
			]
		);
	}

	/**
	 * @return array
	 */
	private function renderDesktop() {
		$out = $this->getContext()->getOutput();
		$modules = $this->getModules();
		$renderedModules = [];
		$out->addModules( 'ext.growthExperiments.Homepage.RecentQuestions' );
		$out->addBodyClasses( 'growthexperiments-homepage-desktop' );
		foreach ( $this->getModuleGroups() as $group => $moduleNames ) {
			$out->addHTML( Html::openElement( 'div', [
				'class' => "growthexperiments-homepage-group-$group",
			] ) );
			foreach ( $moduleNames as $moduleName ) {
				/** @var HomepageModule $module */
				$module = $modules[$moduleName];
				try {
					$out->addHTML( $module->render( HomepageModule::RENDER_DESKTOP ) );
					$renderedModules[$moduleName] = $module;
				} catch ( Exception $exception ) {
					$this->logModuleRenderIssue( $module, $exception );
				} catch ( Error $error ) {
					$this->logModuleRenderIssue( $module, $error );
				}
			}
			$out->addHTML( Html::closeElement( 'div' ) );
		}
		return $renderedModules;
	}

	/**
	 * @param $moduleName
	 * @param HomepageModule $module
	 * @return array
	 */
	private function renderMobileDetails( $moduleName, HomepageModule $module ) {
		$out = $this->getContext()->getOutput();
		$renderedModules = [];
		$out->addBodyClasses( 'growthexperiments-homepage-mobile-details' );

		try {
			$out->addHTML( $module->render( HomepageModule::RENDER_MOBILE_DETAILS ) );
			$renderedModules[ $moduleName ] = $module;
		} catch ( Exception $exception ) {
			$this->logModuleRenderIssue( $module, $exception );
		} catch ( Error $error ) {
			$this->logModuleRenderIssue( $module, $error );
		}
		return $renderedModules;
	}

	/**
	 * @return array
	 */
	private function renderMobileSummary() {
		$out = $this->getContext()->getOutput();
		$modules = $this->getModules();
		$renderedModules = [];
		$out->addBodyClasses( 'growthexperiments-homepage-mobile-summary' );
		foreach ( $modules as $moduleName => $module ) {
			try {
				$out->addHTML( Html::rawElement(
					'a',
					[
						'href' => $this->getPageTitle( $moduleName )->getLinkURL(),
					],
					$module->render( HomepageModule::RENDER_MOBILE_SUMMARY )
				) );
				$renderedModules[$moduleName] = $module;
			} catch ( Exception $exception ) {
				$this->logModuleRenderIssue( $module, $exception );
			} catch ( Error $error ) {
				$this->logModuleRenderIssue( $module, $error );
			}
		}

		return $renderedModules;
	}

	private function outputDataForMobileOverlay() {
		$out = $this->getContext()->getOutput();
		/** @var BaseModule[] $modules */
		$modules = $this->getModules();

		$data = [];
		foreach ( $modules as $moduleName => $module ) {
			try {
				$data[$moduleName] = $module->getDataForOverlay();
			} catch ( Exception $exception ) {
				$this->logModuleRenderIssue( $module, $exception );
			} catch ( Error $error ) {
				$this->logModuleRenderIssue( $module, $error );
			}
		}
		$out->addJsConfigVars( 'homepagemodules', $data );
		$out->addModules( 'ext.growthExperiments.Homepage.Mobile' );
	}

}
