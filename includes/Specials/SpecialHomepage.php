<?php

namespace GrowthExperiments\Specials;

use Config;
use ConfigException;
use DeferredUpdates;
use ErrorPageError;
use ExtensionRegistry;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\EventLogging\SpecialHomepageLogger;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\BaseModule;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use GrowthExperiments\TourHooks;
use GrowthExperiments\Util;
use Html;
use IBufferingStatsdDataFactory;
use MediaWiki\User\UserOptionsManager;
use PrefixingStatsdDataFactoryProxy;
use SpecialPage;
use StatusValue;
use Throwable;
use UserNotLoggedIn;

class SpecialHomepage extends SpecialPage {

	/** @var HomepageModuleRegistry */
	private $moduleRegistry;

	/** @var TrackerFactory */
	private $trackerFactory;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/** @var ExperimentUserManager */
	private $experimentUserManager;

	/** @var Config */
	private $wikiConfig;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @var string Unique identifier for this specific rendering of Special:Homepage.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private $pageviewToken;

	/** @var PrefixingStatsdDataFactoryProxy */
	private $perDbNameStatsdDataFactory;

	/**
	 * @param HomepageModuleRegistry $moduleRegistry
	 * @param TrackerFactory $trackerFactory
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory
	 * @param ExperimentUserManager $experimentUserManager
	 * @param Config $wikiConfig
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		HomepageModuleRegistry $moduleRegistry,
		TrackerFactory $trackerFactory,
		IBufferingStatsdDataFactory $statsdDataFactory,
		PrefixingStatsdDataFactoryProxy $perDbNameStatsdDataFactory,
		ExperimentUserManager $experimentUserManager,
		Config $wikiConfig,
		UserOptionsManager $userOptionsManager
	) {
		parent::__construct( 'Homepage', '', false );
		$this->moduleRegistry = $moduleRegistry;
		$this->trackerFactory = $trackerFactory;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->pageviewToken = $this->generatePageviewToken();
		$this->experimentUserManager = $experimentUserManager;
		$this->wikiConfig = $wikiConfig;
		$this->userOptionsManager = $userOptionsManager;
		$this->perDbNameStatsdDataFactory = $perDbNameStatsdDataFactory;
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
		// Use in client-side performance instrumentation; export as milliseconds as that is what mw.now() uses.
		$this->getOutput()->addJsConfigVars( 'GEHomepageStartTime', round( $startTime * 1000 ) );
		$this->requireLogin();
		parent::execute( $par );
		$this->handleDisabledPreference();
		// Redirect the user to the newcomer task if the page ID in $par can be used
		// to construct a Title object.
		if ( $this->handleNewcomerTask( $par ) ) {
			return;
		}

		$out = $this->getContext()->getOutput();
		$isMobile = Util::isMobile( $out->getSkin() );
		$loggingEnabled = $this->getConfig()->get( 'GEHomepageLoggingEnabled' );
		$userVariant = $this->experimentUserManager->getVariant( $this->getUser() );
		$out->addJsConfigVars( [
			'wgGEHomepagePageviewToken' => $this->pageviewToken,
			'wgGEHomepageLoggingEnabled' => $loggingEnabled,
		] );
		$out->addModules( 'ext.growthExperiments.Homepage.Logging' );
		$out->enableOOUI();
		$out->addModuleStyles( 'ext.growthExperiments.Homepage.styles' );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'growthexperiments-homepage-container ' .
				'growthexperiments-homepage-container-user-variant-' . $userVariant
		] ) );
		$modules = $this->getModules( $isMobile, $par );

		if ( $isMobile ) {
			if (
				array_key_exists( $par, $modules ) &&
				$modules[$par]->supports( IDashboardModule::RENDER_MOBILE_DETAILS )
			) {
				$mode = IDashboardModule::RENDER_MOBILE_DETAILS;
				$this->renderMobileDetails( $modules[$par] );
			} else {
				$mode = IDashboardModule::RENDER_MOBILE_SUMMARY;
				$this->renderMobileSummary();
			}
		} else {
			$mode = IDashboardModule::RENDER_DESKTOP;
			Util::maybeAddGuidedTour(
				$out,
				TourHooks::TOUR_COMPLETED_HOMEPAGE_WELCOME,
				'ext.guidedTour.tour.homepage_welcome',
				$this->userOptionsManager
			);
			$this->renderDesktop();
		}

		$out->addHTML( Html::closeElement( 'div' ) );
		$this->outputJsData( $mode, $modules );
		$this->getOutput()->addBodyClasses(
			'growthexperiments-homepage-user-variant-' .
			$this->experimentUserManager->getVariant( $this->getUser() )
		);
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
			DeferredUpdates::addCallableUpdate( static function () use ( $logger ) {
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
	 * @param bool $isMobile
	 * @param string|null $par Path passed into SpecialHomepage::execute()
	 * @return BaseModule[]
	 */
	private function getModules( bool $isMobile, $par = '' ) {
		$moduleConfig = array_filter( [
			'banner' => true,
			'startemail' => true,
			// Only load start-startediting code (the uninitiated view of suggested edits) for desktop users who
			// haven't activated SE yet.
			'start-startediting' => SuggestedEdits::isEnabledForAnyone(
				$this->getContext()->getConfig()
			) && ( !$par && !$isMobile &&
				!SuggestedEdits::isActivated( $this->getContext(), $this->userOptionsManager )
			),
			'suggested-edits' => SuggestedEdits::isEnabled( $this->getContext() ),
			'impact' => true,
			'mentorship' => $this->wikiConfig->get( 'GEMentorshipEnabled' ),
			'help' => true,
		] );
		$modules = [];
		foreach ( $moduleConfig as $moduleId => $_ ) {
			$modules[$moduleId] = $this->moduleRegistry->get( $moduleId, $this->getContext() );
		}
		return $modules;
	}

	/**
	 * @return string[][][]
	 */
	private function getModuleGroups(): array {
		return [
			'main' => [
				'primary' => [ 'banner', 'startemail' ],
				'secondary' => [ 'start-startediting', 'suggested-edits' ]
			],
			'sidebar' => [
				'primary' => [ 'impact' ],
				'secondary' => [ 'mentorship', 'help' ]
			]
		];
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
		$modules = $this->getModules( false );
		$out->addBodyClasses( 'growthexperiments-homepage-desktop' );
		foreach ( $this->getModuleGroups() as $group => $subGroups ) {
			$out->addHTML( Html::openElement( 'div', [
				'class' => "growthexperiments-homepage-group-$group " .
					"growthexperiments-homepage-group-$group-user-variant-" .
					$this->experimentUserManager->getVariant( $this->getUser() ),
			] ) );
			foreach ( $subGroups as $subGroup => $moduleNames ) {
				$out->addHTML( Html::openElement( 'div', [
					'class' => "growthexperiments-homepage-group-$group-subgroup-$subGroup " .
						"growthexperiments-homepage-group-$group-subgroup-$subGroup-user-variant-" .
						$this->experimentUserManager->getVariant( $this->getUser() )
				] ) );
				foreach ( $moduleNames as $moduleName ) {
					/** @var IDashboardModule $module */
					$module = $modules[$moduleName] ?? null;
					if ( !$module ) {
						continue;
					}
					$html = $this->getModuleRenderHtmlSafe( $module, IDashboardModule::RENDER_DESKTOP );
					$out->addHTML( $html );
				}
				$out->addHTML( Html::closeElement( 'div' ) );
			}
			$out->addHTML( Html::closeElement( 'div' ) );
		}
	}

	/**
	 * @param IDashboardModule $module
	 */
	private function renderMobileDetails( IDashboardModule $module ) {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( 'growthexperiments-homepage-mobile-details' );
		$html = $this->getModuleRenderHtmlSafe( $module, IDashboardModule::RENDER_MOBILE_DETAILS );
		$this->getOutput()->addHTML( $html );
	}

	/**
	 * @param string $moduleName
	 * @param string $moduleHtml
	 * @return string
	 */
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
		$modules = $this->getModules( true );
		$isOpeningOverlay = $this->getContext()->getRequest()->getFuzzyBool( 'overlay' );
		$out->addBodyClasses( [
			'growthexperiments-homepage-mobile-summary',
			$isOpeningOverlay ? 'growthexperiments-homepage-mobile-summary--opening-overlay' : ''
		] );
		foreach ( $modules as $moduleName => $module ) {
			$html = $this->getModuleRenderHtmlSafe( $module, IDashboardModule::RENDER_MOBILE_SUMMARY );
			if ( $module->supports( IDashboardModule::RENDER_MOBILE_DETAILS ) ) {
				$html = $this->wrapMobileSummaryWithLink( $moduleName, $html );
			}
			$this->getOutput()->addHTML( $html );
		}
	}

	/**
	 * Get the module render HTML for a particular mode, catching exceptions by default.
	 *
	 * If GEDeveloperSetup is on, then throw the exceptions.
	 * @param IDashboardModule $module
	 * @param string $mode
	 * @throws Throwable
	 * @return string
	 */
	private function getModuleRenderHtmlSafe( IDashboardModule $module, string $mode ): string {
		$html = '';
		try {
			$html = $module->render( $mode );
		} catch ( Throwable $throwable ) {
			if ( $this->getConfig()->get( 'GEDeveloperSetup' ) ) {
				throw $throwable;
			}
			Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
		}
		return $html;
	}

	/**
	 * @param string $mode One of RENDER_DESKTOP, RENDER_MOBILE_SUMMARY, RENDER_MOBILE_DETAILS
	 * @param IDashboardModule[] $modules
	 */
	private function outputJsData( $mode, array $modules ) {
		$out = $this->getContext()->getOutput();

		$data = [];
		$html = '';
		foreach ( $modules as $moduleName => $module ) {
			try {
				$data[$moduleName] = $module->getJsData( $mode );
				if ( isset( $data[$moduleName]['html'] ) && $mode === IDashboardModule::RENDER_MOBILE_SUMMARY ) {
					// This is slightly ugly, but making modules generate special-page-based
					// links to themselves would be uglier.
					if ( $module->supports( IDashboardModule::RENDER_MOBILE_DETAILS ) ) {
						$data[$moduleName]['html'] = $this->wrapMobileSummaryWithLink( $moduleName,
							$data[$moduleName]['html'] );
					}
				}
				if ( isset( $data[$moduleName]['overlay'] ) ) {
					$html .= $data[$moduleName]['overlay'];
					unset( $data[$moduleName]['overlay'] );
				}
			} catch ( Throwable $throwable ) {
				if ( $this->getConfig()->get( 'GEDeveloperSetup' ) ) {
					throw $throwable;
				}
				Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
			}
		}
		$out->addJsConfigVars( 'homepagemodules', $data );

		if ( $mode === IDashboardModule::RENDER_MOBILE_SUMMARY ) {
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
		$request = $this->getRequest();
		$titleId = (int)explode( '/', $par )[1];
		$clickId = $request->getVal( 'geclickid' );
		$newcomerTaskToken = $request->getVal( 'genewcomertasktoken' );
		$taskTypeId = $request->getVal( 'getasktype', '' );

		$tracker = $this->trackerFactory->getTracker( $this->getUser() );
		if ( $tracker->track( $titleId, $taskTypeId, $clickId, $newcomerTaskToken ) instanceof StatusValue ) {
			// If a StatusValue is returned from ->track(), it's because loading the task type or
			//  title failed, so don't attempt to redirect the user. If track returns false
			// (storing the value in cache failed) then we are not going to prevent redirection.
			return false;
		}
		$suggestedEdits = $this->getModules( Util::isMobile( $this->getSkin() ) )[ 'suggested-edits' ];
		$redirectParams = array_merge(
			[ 'getasktype' => $request->getVal( 'getasktype' ),
				// This query parameter allows us to load the help panel for the suggested edit session,
				// even if the user has the preference (probably unknowingly) disabled.
				'gesuggestededit' => 1 ],
			$suggestedEdits instanceof SuggestedEdits ? $suggestedEdits->getRedirectParams( $taskTypeId ) : []
		);
		$this->perDbNameStatsdDataFactory->increment( 'GrowthExperiments.NewcomerTask.' . $taskTypeId . '.Click' );
		$this->getOutput()->redirect(
			$tracker->getTitleUrl( $redirectParams )
		);
		return true;
	}

}
