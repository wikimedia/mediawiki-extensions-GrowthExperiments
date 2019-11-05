<?php

namespace GrowthExperiments\Specials;

use ConfigException;
use DeferredUpdates;
use ErrorPageError;
use Exception;
use ExtensionRegistry;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\EventLogging\SpecialHomepageLogger;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\BaseModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\TourHooks;
use GrowthExperiments\Util;
use Html;
use GrowthExperiments\HomepageModules\Start;
use MediaWiki\Extensions\PageViewInfo\PageViewService;
use MediaWiki\Session\SessionManager;
use SpecialPage;
use Throwable;
use Title;
use UserNotLoggedIn;

class SpecialHomepage extends SpecialPage {

	/** @var EditInfoService */
	private $editInfoService;

	/** @var PageViewService|null */
	private $pageViewService;

	/**
	 * @var string Unique identifier for this specific rendering of Special:Homepage.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private $pageviewToken;

	/**
	 * @param EditInfoService $editInfoService
	 * @param PageViewService|null $pageViewService
	 */
	public function __construct(
		EditInfoService $editInfoService,
		PageViewService $pageViewService = null
	) {
		parent::__construct( 'Homepage', '', false );
		$this->editInfoService = $editInfoService;
		$this->pageViewService = $pageViewService;
		$this->pageviewToken = $this->generateUniqueToken();
		// Hack: Making the userpage the relevant title for the homepage
		// allows using the talk overlay for the talk tab on mobile.
		// This is done only for the mobile skin, because on Vector setting relevant
		// title results in {Create/Edit}/History/Watchlist etc tabs added to the page,
		// since Vector assumes that we are dealing with an editable user page and outputs
		// the relevant controls. See T229263.
		if ( Util::isMobile( $this->getSkin() ) ) {
			$this->getSkin()->setRelevantTitle( $this->getUser()->getUserPage() );
		}
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
			'wgGEHomepageLoggingEnabled' => $loggingEnabled,
			'wgGERestbaseUrl' => Util::getRestbaseUrl( $this->getConfig() ),
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
			// Display the homepage_welcome tour, but only if the user hasn't already seen the
			// homepage_discovery tour (T229044)
			if ( !$this->getUser()->getBoolOption( TourHooks::TOUR_COMPLETED_HOMEPAGE_DISCOVERY ) ) {
				Util::maybeAddGuidedTour(
					$out,
					TourHooks::TOUR_COMPLETED_HOMEPAGE_WELCOME,
					'ext.guidedTour.tour.homepage_welcome'
				);
			}
			$renderedModules = $this->renderDesktop();
		}

		$out->addHTML( Html::closeElement( 'div' ) );

		if ( $isMobile && !$par ) {
			$this->outputDataForMobileOverlay( $renderedModules );
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
		$modules = [
			'start' => new Start( $this->getContext() ),
			'suggested-edits' => null,
			'impact' => new Impact( $this->getContext() ),
			'mentorship' => new Mentorship( $this->getContext() ),
			'help' => new Help( $this->getContext() ),
		];
		if ( SuggestedEdits::isEnabled( $this->getContext() ) ) {
			// TODO use some kind of registry instead of passing things through here
			$modules['suggested-edits'] = new SuggestedEdits( $this->getContext(),
				$this->editInfoService, $this->pageViewService );
		}
		return array_filter( $modules );
	}

	private function getModuleGroups() {
		if ( SuggestedEdits::isEnabled( $this->getContext() )
			&& SuggestedEdits::isActivated( $this->getContext() )
		) {
			return [
				'main' => [ 'start', 'suggested-edits', 'impact' ],
				'sidebar' => [ 'mentorship', 'help' ],
			];
		} else {
			return [
				'main' => [ 'start', 'impact', 'mentorship' ],
				'sidebar' => [ 'help' ],
			];
		}
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
					Util::logError( $exception, [ 'origin' => __METHOD__ ] );
				} catch ( Throwable $throwable ) {
					Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
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
			Util::logError( $exception, [ 'origin' => __METHOD__ ] );
		} catch ( Throwable $throwable ) {
			Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
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
				Util::logError( $exception, [ 'origin' => __METHOD__ ] );
			} catch ( Throwable $throwable ) {
				Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
			}
		}

		return $renderedModules;
	}

	/**
	 * @param BaseModule[] $modules
	 */
	private function outputDataForMobileOverlay( array $modules ) {
		$out = $this->getContext()->getOutput();

		$data = [];
		$html = '';
		foreach ( $modules as $moduleName => $module ) {
			try {
				$data[$moduleName] = $module->getDataForOverlay();
				$html .= $data[$moduleName]['html'];
				unset( $data[$moduleName]['html'] );
			} catch ( Exception $exception ) {
				Util::logError( $exception, [ 'origin' => __METHOD__ ] );
			} catch ( Throwable $throwable ) {
				Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
			}
		}
		$out->addJsConfigVars( [
			'homepagemobile' => true,
			'homepagemodules' => $data,
		] );
		$out->addModules( 'ext.growthExperiments.Homepage.Mobile' );
		$out->addHTML( Html::rawElement(
			'div',
			[ 'class' => 'growthexperiments-homepage-overlay-container' ],
			$html
		) );
	}

}
