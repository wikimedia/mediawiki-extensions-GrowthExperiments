<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\EventLogging\SpecialHomepageLogger;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\BaseModule;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\TourHooks;
use GrowthExperiments\Util;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\WikiMap\WikiMap;
use Throwable;
use Wikimedia\Stats\StatsFactory;

class SpecialHomepage extends SpecialPage {

	private HomepageModuleRegistry $moduleRegistry;
	private AbstractExperimentManager $experimentUserManager;
	private IMentorManager $mentorManager;
	private Config $wikiConfig;
	private UserOptionsManager $userOptionsManager;

	/**
	 * @var string Unique identifier for this specific rendering of Special:Homepage.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private string $pageviewToken;
	private TitleFactory $titleFactory;
	private bool $isMobile;
	private StatsFactory $statsFactory;

	public function __construct(
		HomepageModuleRegistry $moduleRegistry,
		StatsFactory $statsFactory,
		AbstractExperimentManager $experimentUserManager,
		IMentorManager $mentorManager,
		Config $wikiConfig,
		UserOptionsManager $userOptionsManager,
		TitleFactory $titleFactory
	) {
		parent::__construct( 'Homepage', '', false );
		$this->moduleRegistry = $moduleRegistry;
		$this->statsFactory = $statsFactory;
		$this->pageviewToken = $this->generatePageviewToken();
		$this->experimentUserManager = $experimentUserManager;
		$this->mentorManager = $mentorManager;
		$this->wikiConfig = $wikiConfig;
		$this->userOptionsManager = $userOptionsManager;
		$this->titleFactory = $titleFactory;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
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
		$this->requireNamedUser();
		parent::execute( $par );
		$this->handleDisabledPreference();
		// Redirect the user to the newcomer task if the page ID in $par can be used
		// to construct a Title object.
		if ( $this->handleNewcomerTask( $par ) ) {
			return;
		}

		$out = $this->getContext()->getOutput();
		$this->isMobile = Util::isMobile( $out->getSkin() );
		$userVariant = $this->experimentUserManager->getVariant( $this->getUser() );
		$out->addJsConfigVars( [
			'wgGEHomepagePageviewToken' => $this->pageviewToken,
			'wgGEUseMetricsPlatformExtension' => Util::useMetricsPlatform(),
		] );
		$out->addModules( 'ext.growthExperiments.Homepage' );
		$out->enableOOUI();
		$out->addModuleStyles( [ 'ext.growthExperiments.Homepage.styles' ] );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'growthexperiments-homepage-container ' .
				'growthexperiments-homepage-container-user-variant-' . $userVariant,
		] ) );
		$modules = $this->getModules( $this->isMobile, $par );

		if ( $this->isMobile ) {
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
		$platform = ( $this->isMobile ? 'mobile' : 'desktop' );
		$overallSsrTimeInSeconds = microtime( true ) - $startTime;
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getTiming( 'special_homepage_server_side_render_seconds' )
			->setLabel( 'platform', $platform )
			->observeSeconds( $overallSsrTimeInSeconds );

		if ( ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) &&
			 count( $modules ) ) {
			$logger = new SpecialHomepageLogger(
				$this->pageviewToken,
				$this->getContext()->getUser(),
				$this->getRequest(),
				$this->isMobile,
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
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-homepage-specialpage-title' )
			->params( $this->getUser()->getName() );
	}

	/**
	 * @param bool $isMobile
	 * @param string|null $par Path passed into SpecialHomepage::execute()
	 * @return BaseModule[]
	 */
	private function getModules( bool $isMobile, $par = '' ) {
		$mentorshipState = $this->mentorManager->getMentorshipStateForUser( $this->getUser() );
		$moduleConfig = array_filter( [
			'banner' => true,
			'welcomesurveyreminder' => true,
			'startemail' => !$this->getUser()->isEmailConfirmed(),
			// Only load start-startediting code (the uninitiated view of suggested edits) for desktop users who
			// haven't activated SE yet.
			'start-startediting' => SuggestedEdits::isEnabledForAnyone(
				$this->getContext()->getConfig()
			) && ( !$par && !$isMobile &&
				!SuggestedEdits::isActivated( $this->getUser(), $this->userOptionsManager )
			),
			'suggested-edits' => SuggestedEdits::isEnabledForAnyone( $this->getConfig() ),
			'community-updates' => $this->getConfig()->get( 'GECommunityUpdatesEnabled' ),
			'impact' => true,
			'mentorship' => $this->wikiConfig->get( 'GEMentorshipEnabled' ) &&
				$mentorshipState === IMentorManager::MENTORSHIP_ENABLED,
			'mentorship-optin' => $this->wikiConfig->get( 'GEMentorshipEnabled' ) &&
				$mentorshipState === IMentorManager::MENTORSHIP_OPTED_OUT,
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
		$isSuggestedEditsEnabled = SuggestedEdits::isEnabledForAnyone(
			$this->getContext()->getConfig()
		);
		return [
			'main' => [
				'primary' => [ 'banner', 'welcomesurveyreminder', 'startemail' ],
				'secondary' => $isSuggestedEditsEnabled ?
					[ 'start-startediting', 'suggested-edits' ] :
					[ 'impact' ],
			],
			'sidebar' => [
				'primary' => array_merge(
					[ 'community-updates' ],
					$isSuggestedEditsEnabled ? [ 'impact' ] : []
				),
				'secondary' => [ 'mentorship', 'mentorship-optin', 'help' ],
			],
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
						$this->experimentUserManager->getVariant( $this->getUser() ),
				] ) );
				foreach ( $moduleNames as $moduleName ) {
					/** @var IDashboardModule $module */
					$module = $modules[$moduleName] ?? null;
					if ( !$module ) {
						continue;
					}

					$startTime = microtime( true );

					$module->setPageURL( $this->getPageTitle()->getLinkURL() );
					$html = $this->getModuleRenderHtmlSafe( $module, IDashboardModule::RENDER_DESKTOP );
					$out->addHTML( $html );

					$this->recordModuleRenderingTime(
						$moduleName,
						IDashboardModule::RENDER_DESKTOP,
						microtime( true ) - $startTime
					);
				}
				$out->addHTML( Html::closeElement( 'div' ) );
			}
			$out->addHTML( Html::closeElement( 'div' ) );
		}
	}

	private function recordModuleRenderingTime( string $moduleName, string $mode, float $timeToRecordInSeconds ): void {
		$wiki = WikiMap::getCurrentWikiId();
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getTiming( 'special_homepage_ssr_per_module_seconds' )
			->setLabel( 'wiki', $wiki )
			->setLabel( 'module', $moduleName )
			->setLabel( 'mode', $mode )
			->observeSeconds( $timeToRecordInSeconds );
	}

	private function renderMobileDetails( IDashboardModule $module ) {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( 'growthexperiments-homepage-mobile-details' );
		$html = $this->getModuleRenderHtmlSafe( $module, IDashboardModule::RENDER_MOBILE_DETAILS );
		$this->getOutput()->addHTML( $html );
	}

	private function renderMobileSummary() {
		$out = $this->getContext()->getOutput();
		$modules = $this->getModules( true );
		$isOpeningOverlay = $this->getContext()->getRequest()->getFuzzyBool( 'overlay' );
		$out->addBodyClasses( [
			'growthexperiments-homepage-mobile-summary',
			$isOpeningOverlay ? 'growthexperiments-homepage-mobile-summary--opening-overlay' : '',
		] );
		foreach ( $modules as $moduleName => $module ) {
			$startTime = microtime( true );

			$module->setPageURL( $this->getPageTitle()->getLinkURL() );
			$html = $this->getModuleRenderHtmlSafe( $module, IDashboardModule::RENDER_MOBILE_SUMMARY );
			$this->getOutput()->addHTML( $html );

			$this->recordModuleRenderingTime(
				$moduleName,
				IDashboardModule::RENDER_MOBILE_SUMMARY,
				microtime( true ) - $startTime
			);
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
			$out->addModules( 'ext.growthExperiments.Homepage.mobile' );
			$out->addHTML( Html::rawElement(
				'div',
				[ 'class' => 'growthexperiments-homepage-overlay-container' ],
				$html
			) );
		}
	}

	/**
	 * @param string|null $par The URL path arguments after Special:Homepage
	 * @return bool
	 */
	private function handleNewcomerTask( ?string $par = null ): bool {
		if ( !$par ||
			!str_starts_with( $par, 'newcomertask/' ) ||
			!SuggestedEdits::isEnabledForAnyone( $this->getConfig() )
		) {
			return false;
		}
		$titleId = (int)explode( '/', $par )[1];
		if ( !$titleId ) {
			return false;
		}
		$title = $this->titleFactory->newFromID( $titleId );
		if ( !$title ) {
			// Will bring the user back to Special:Homepage, since we couldn't load a title.
			return false;
		}

		$request = $this->getRequest();
		$clickId = $request->getVal( 'geclickid' );
		$newcomerTaskToken = $request->getVal( 'genewcomertasktoken' );
		$taskTypeId = $request->getVal( 'getasktype', '' );
		$missing = [];
		if ( !$clickId ) {
			$missing[] = 'geclickid';
		}
		if ( !$newcomerTaskToken ) {
			$missing[] = 'genewcomertasktoken';
		}
		if ( !$taskTypeId ) {
			$missing[] = 'getasktype';
		}
		if ( count( $missing ) ) {
			// Something is broken in our client-side code; these params should always be present.
			$errorMessage = sprintf(
				'Invalid parameters passed to Special:Homepage/newcomertask. Missing params: %s',
				implode( ',', $missing )
			);
			LoggerFactory::getInstance( 'GrowthExperiments' )->error( $errorMessage );
			if ( $this->getConfig()->get( 'GEDeveloperSetup' ) ) {
				// For developer setup wikis (local + beta/CI), throw an exception so we can
				// catch the issue in testing/CI. For production, we should
				// let the user go on to the task, even if we lose analytics for that interaction.
				throw new InvalidArgumentException( $errorMessage );
			}
		}

		$suggestedEdits = $this->getModules( Util::isMobile( $this->getSkin() ) )[ 'suggested-edits' ];
		$redirectParams = array_merge(
			[
				'getasktype' => $request->getVal( 'getasktype' ),
				// This query parameter allows us to load the help panel for the suggested edit session,
				// even if the user has the preference (probably unknowingly) disabled.
				'gesuggestededit' => 1,
				'geclickid' => $clickId,
				'genewcomertasktoken' => $newcomerTaskToken,
				// Query parameter to show the onboarding Vue dialog
				'new-onboarding' => $request->getVal( 'new-onboarding' ),
				'gerecommendationid' => $request->getVal( 'gerecommendationid' ),
				'surfaced' => $request->getVal( 'surfaced' ),
			],
			$suggestedEdits instanceof SuggestedEdits ? $suggestedEdits->getRedirectParams( $taskTypeId ) : []
		);

		$statsAction = 'Click';
		$wiki = WikiMap::getCurrentWikiId();
		$this->statsFactory->withComponent( 'GrowthExperiments' )
				->getCounter( 'newcomertask_total' )
				->setLabel( 'taskType', $taskTypeId ?? '' )
				->setLabel( 'wiki', $wiki )
				->setLabel( 'action', $statsAction )
				->increment();

		$this->getOutput()->redirect(
			$title->getFullUrlForRedirect( $redirectParams )
		);
		return true;
	}

}
