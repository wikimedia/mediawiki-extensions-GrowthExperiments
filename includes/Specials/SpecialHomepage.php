<?php

namespace GrowthExperiments\Specials;

use ConfigException;
use DeferredUpdates;
use ErrorPageError;
use Exception;
use ExtensionRegistry;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\EventLogging\SpecialHomepageLogger;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\BaseModule;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\Start;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Tracker\Tracker;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use GrowthExperiments\TourHooks;
use GrowthExperiments\Util;
use Html;
use IBufferingStatsdDataFactory;
use MediaWiki\Extensions\PageViewInfo\PageViewService;
use SpecialPage;
use StatusValue;
use Throwable;
use Title;
use UserNotLoggedIn;
use Wikimedia\Rdbms\IDatabase;

class SpecialHomepage extends SpecialPage {

	/** @var EditInfoService */
	private $editInfoService;

	/** @var IDatabase */
	private $dbr;

	/** @var PageViewService|null */
	private $pageViewService;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var Tracker */
	private $tracker;

	/**
	 * @var string Unique identifier for this specific rendering of Special:Homepage.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private $pageviewToken;
	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $statsdDataFactory;
	/**
	 * @var ExperimentUserManager
	 */
	private $experimentUserManager;

	/**
	 * @param EditInfoService $editInfoService
	 * @param IDatabase $dbr
	 * @param ConfigurationLoader $configurationLoader
	 * @param TrackerFactory $trackerFactory
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param ExperimentUserManager $experimentUserManager
	 * @param PageViewService|null $pageViewService
	 */
	public function __construct(
		EditInfoService $editInfoService,
		IDatabase $dbr,
		ConfigurationLoader $configurationLoader,
		TrackerFactory $trackerFactory,
		IBufferingStatsdDataFactory $statsdDataFactory,
		ExperimentUserManager $experimentUserManager,
		PageViewService $pageViewService = null
	) {
		parent::__construct( 'Homepage', '', false );
		$this->editInfoService = $editInfoService;
		$this->dbr = $dbr;
		$this->configurationLoader = $configurationLoader;
		$this->tracker = $trackerFactory->getTracker( $this->getUser() );
		$this->statsdDataFactory = $statsdDataFactory;
		$this->pageViewService = $pageViewService;
		$this->pageviewToken = $this->generatePageviewToken();

		// Hack: Making the userpage the relevant title for the homepage
		// allows using the talk overlay for the talk tab on mobile.
		// This is done only for the mobile skin, because on Vector setting relevant
		// title results in {Create/Edit}/History/Watchlist etc tabs added to the page,
		// since Vector assumes that we are dealing with an editable user page and outputs
		// the relevant controls. See T229263.
		if ( Util::isMobile( $this->getSkin() ) ) {
			$this->getSkin()->setRelevantTitle( $this->getUser()->getUserPage() );
		}
		$this->experimentUserManager = $experimentUserManager;
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
				$user = $user->getInstanceForUpdate();
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
		$startTime = microtime( true );
		$this->requireLogin();
		parent::execute( $par );
		$this->handleDisabledPreference();
		if ( $this->handleTutorialVisit( $par ) ) {
			return;
		}
		// Redirect the user to the newcomer task if the page ID in $par can be used
		// to construct a Title object.
		if ( $this->handleNewcomerTask( $par ) ) {
			return;
		}

		$out = $this->getContext()->getOutput();
		$isMobile = Util::isMobile( $out->getSkin() );
		$loggingEnabled = $this->getConfig()->get( 'GEHomepageLoggingEnabled' );
		$out->addJsConfigVars( [
			'wgGEHomepagePageviewToken' => $this->pageviewToken,
			'wgGEHomepageLoggingEnabled' => $loggingEnabled,
		] );
		$out->addModules( 'ext.growthExperiments.Homepage.Logging' );
		$out->enableOOUI();
		$out->addModuleStyles( 'ext.growthExperiments.Homepage.styles' );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'growthexperiments-homepage-container',
		] ) );
		$modules = $this->getModules();

		if ( $isMobile ) {
			if ( array_key_exists( $par, $modules ) ) {
				$mode = HomepageModule::RENDER_MOBILE_DETAILS;
				$this->renderMobileDetails( $modules[$par] );
			} else {
				$mode = HomepageModule::RENDER_MOBILE_SUMMARY;
				$this->renderMobileSummary();
			}
		} else {
			$mode = HomepageModule::RENDER_DESKTOP;
			// Display the homepage_welcome tour, but only if the user hasn't already seen the
			// homepage_discovery tour (T229044)
			if ( !$this->getUser()->getBoolOption( TourHooks::TOUR_COMPLETED_HOMEPAGE_DISCOVERY ) ) {
				Util::maybeAddGuidedTour(
					$out,
					TourHooks::TOUR_COMPLETED_HOMEPAGE_WELCOME,
					'ext.guidedTour.tour.homepage_welcome'
				);
			}
			$this->renderDesktop();
		}

		$out->addHTML( Html::closeElement( 'div' ) );
		$this->outputJsData( $mode, $modules );
		$this->statsdDataFactory->timing(
			'timing.growthExperiments.specialHomepage.serverSideRender.' . ( $isMobile ? 'mobile' : 'desktop' ),
			microtime( true ) - $startTime
		);

		if ( $loggingEnabled &&
			 ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) &&
			 count( $modules ) ) {
			$logger = new SpecialHomepageLogger(
				$this->pageviewToken,
				$this->getContext()->getUser(),
				$this->getRequest(),
				$isMobile,
				$modules
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
	 * @return BaseModule[]
	 */
	private function getModules() {
		$modules = [
			'start' => new Start( $this->getContext(), $this->experimentUserManager ),
			'suggested-edits' => null,
			'impact' => new Impact(
				$this->getContext(),
				$this->dbr,
				$this->experimentUserManager,
				$this->pageViewService
			),
			'mentorship' => new Mentorship( $this->getContext(), $this->experimentUserManager ),
			'help' => new Help( $this->getContext(), $this->experimentUserManager ),
		];
		if ( SuggestedEdits::isEnabled( $this->getContext() ) ) {
			// TODO use some kind of registry instead of passing things through here
			$modules['suggested-edits'] = new SuggestedEdits(
				$this->getContext(),
				$this->editInfoService,
				$this->experimentUserManager,
				$this->pageViewService,
				$this->configurationLoader
			);
		}
		return array_filter( $modules );
	}

	/**
	 * @return string[][]
	 */
	private function getModuleGroups() : array {
		if ( in_array(
			$this->experimentUserManager->getVariant( $this->getUser() ),
			[ 'C', 'D' ]
		) ) {
			return [
				'main' => [ 'suggested-edits' ],
				'sidebar' => [ 'impact', 'mentorship', 'help' ]
			];
		}

		// else: variant A
		if ( SuggestedEdits::isEnabled( $this->getContext() )
			&& SuggestedEdits::isActivated( $this->getContext() )
		) {
			return [
				'main' => [ 'start', 'suggested-edits', 'impact' ],
				'sidebar' => [ 'mentorship', 'help' ],
			];
		} else {
			return [
				'main' => [ 'start', 'suggested-edits', 'impact', 'mentorship' ],
				'sidebar' => [ 'help' ],
			];
		}
	}

	/**
	 * Returns 32-character random string.
	 * The token is used for client-side logging and can be retrieved on Special:Homepage via the
	 * wgGEHomepagePageviewToken JS variable.
	 * @return string
	 */
	private function generatePageviewToken() {
		return \Wikimedia\base_convert( \MWCryptRand::generateHex( 40 ), 16, 32, 32 );
	}

	private function renderDesktop() {
		$out = $this->getContext()->getOutput();
		$modules = $this->getModules();
		$out->addBodyClasses( 'growthexperiments-homepage-desktop' );
		foreach ( $this->getModuleGroups() as $group => $moduleNames ) {
			$out->addHTML( Html::openElement( 'div', [
				'class' => "growthexperiments-homepage-group-$group " .
					"growthexperiments-homepage-group-$group-user-variant-" .
					$this->experimentUserManager->getVariant( $this->getUser() ),
			] ) );
			foreach ( $moduleNames as $moduleName ) {
				/** @var HomepageModule $module */
				$module = $modules[$moduleName] ?? null;
				if ( !$module ) {
					continue;
				}
				try {
					$out->addHTML( $module->render( HomepageModule::RENDER_DESKTOP ) );
				} catch ( Exception $exception ) {
					Util::logError( $exception, [ 'origin' => __METHOD__ ] );
				} catch ( Throwable $throwable ) {
					Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
				}
			}
			$out->addHTML( Html::closeElement( 'div' ) );
		}
	}

	/**
	 * @param HomepageModule $module
	 */
	private function renderMobileDetails( HomepageModule $module ) {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( 'growthexperiments-homepage-mobile-details' );

		try {
			$out->addHTML( $module->render( HomepageModule::RENDER_MOBILE_DETAILS ) );
		} catch ( Exception $exception ) {
			Util::logError( $exception, [ 'origin' => __METHOD__ ] );
		} catch ( Throwable $throwable ) {
			Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
		}
	}

	private function wrapMobileSummaryWithLink( $moduleName, $moduleHtml ) {
		if ( $moduleHtml ) {
			$moduleHtml = Html::rawElement( 'a', [
				'href' => $this->getPageTitle( $moduleName )->getLinkURL(),
			], $moduleHtml );
		}
		return $moduleHtml;
	}

	private function renderMobileSummary() {
		$out = $this->getContext()->getOutput();
		$modules = $this->getModules();
		$out->addBodyClasses( 'growthexperiments-homepage-mobile-summary' );
		foreach ( $modules as $moduleName => $module ) {
			try {
				$out->addHTML( $this->wrapMobileSummaryWithLink( $moduleName,
					$module->render( HomepageModule::RENDER_MOBILE_SUMMARY ) ) );
			} catch ( Exception $exception ) {
				Util::logError( $exception, [ 'origin' => __METHOD__ ] );
			} catch ( Throwable $throwable ) {
				Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
			}
		}
	}

	/**
	 * @param string $mode One of RENDER_DESKTOP, RENDER_MOBILE_SUMMARY, RENDER_MOBILE_DETAILS
	 * @param HomepageModule[] $modules
	 */
	private function outputJsData( $mode, array $modules ) {
		$out = $this->getContext()->getOutput();

		$data = [];
		$html = '';
		foreach ( $modules as $moduleName => $module ) {
			try {
				$data[$moduleName] = $module->getJsData( $mode );
				if ( isset( $data[$moduleName]['html'] ) && $mode === HomepageModule::RENDER_MOBILE_SUMMARY ) {
					// This is slightly ugly, but making modules generate special-page-based
					// links to themselves would be uglier.
					$data[$moduleName]['html'] = $this->wrapMobileSummaryWithLink( $moduleName,
						$data[$moduleName]['html'] );
				}
				if ( isset( $data[$moduleName]['overlay'] ) ) {
					$html .= $data[$moduleName]['overlay'];
					unset( $data[$moduleName]['overlay'] );
				}
			} catch ( Exception $exception ) {
				Util::logError( $exception, [ 'origin' => __METHOD__ ] );
			} catch ( Throwable $throwable ) {
				Util::logError( $throwable, [ 'origin' => __METHOD__ ] );
			}
		}
		$out->addJsConfigVars( 'homepagemodules', $data );

		if ( $mode === HomepageModule::RENDER_MOBILE_SUMMARY ) {
			$out->addJsConfigVars( 'homepagemobile', true );
			$out->addModules( 'ext.growthExperiments.Homepage.Mobile' );
			$out->addHTML( Html::rawElement(
				'div',
				[ 'class' => 'growthexperiments-homepage-overlay-container' ],
				$html
			) );
		}
	}

	private function handleNewcomerTask( string $par = null ) {
		if ( !$par || strpos( $par, 'newcomertask/' ) !== 0 ||
			 !SuggestedEdits::isEnabled( $this->getContext() ) ) {
			return false;
		}
		$titleId = (int)explode( '/', $par )[1];
		$request = $this->getRequest();
		$clickId = $request->getVal( 'geclickid' );
		if ( $this->tracker->track( $titleId, $clickId ) instanceof StatusValue ) {
			// If a StatusValue is returned from ->track(), it's because constructing the title
			// from page ID failed, so don't attempt to redirect the user. If track returns false
			// (storing the value in cache failed) then we are not going to prevent redirection.
			return false;
		}
		$this->getOutput()->redirect(
			$this->tracker->getTitleUrl( [ 'getasktype' => $request->getVal( 'getasktype' ) ] )
		);
		return true;
	}

}
