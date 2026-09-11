<?php

namespace GrowthExperiments;

use GrowthExperiments\Homepage\SiteNoticeGenerator;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Skin\Hook\SiteNoticeAfterHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\Options\UserOptionsLookup;

class TourHooks implements
	BeforePageDisplayHook,
	ResourceLoaderRegisterModulesHook,
	GetPreferencesHook,
	UserGetDefaultOptionsHook,
	SiteNoticeAfterHook
{

	public const TOUR_COMPLETED_HELP_PANEL = 'growthexperiments-tour-help-panel';
	public const TOUR_COMPLETED_HOMEPAGE_MENTORSHIP = 'growthexperiments-tour-homepage-mentorship';
	public const TOUR_COMPLETED_HOMEPAGE_WELCOME = 'growthexperiments-tour-homepage-welcome';
	public const TOUR_COMPLETED_HOMEPAGE_DISCOVERY = 'growthexperiments-tour-homepage-discovery';

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly FeatureManager $featureManager,
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		// Show the discovery tour if the user isn't on WelcomeSurvey or Homepage.
		// If they have already seen the welcome tour, don't show the discovery one.
		if ( !$out->getTitle()->isSpecial( 'WelcomeSurvey' ) &&
			 !$out->getTitle()->isSpecial( 'Homepage' ) &&
			 HomepageHooks::isHomepageEnabled( $out->getUser() ) &&
			 !Util::isMobile( $skin ) &&
			 !$this->userOptionsLookup->getBoolOption( $out->getUser(), self::TOUR_COMPLETED_HOMEPAGE_WELCOME )
		) {
			Util::maybeAddGuidedTour(
				$out,
				self::TOUR_COMPLETED_HOMEPAGE_DISCOVERY,
				'ext.guidedTour.tour.homepage_discovery',
				$this->userOptionsLookup
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onSiteNoticeAfter( &$siteNotice, $skin ) {
		$user = $skin->getUser();
		if ( !HomepageHooks::isHomepageEnabled( $user ) ) {
			return true;
		}
		if ( !$this->featureManager->isEarlyOnboardingExperimentTreatment( $user ) ) {
			return true;
		}

		$out = $skin->getOutput();
		$title = $out->getTitle();
		if ( !$title || !$title->isContentPage() ) {
			return true;
		}

		$welcomeOption = $this->userOptionsLookup->getOption( $user, self::TOUR_COMPLETED_HOMEPAGE_WELCOME );
		if (
			$welcomeOption !== '0.5'
		) {
			return true;
		}

		$request = $out->getRequest();
		if ( self::isEditorOpen( $request ) || self::isSuggestedEditRequest( $request ) ) {
			return true;
		}

		if ( Util::isMobile( $skin ) ) {
			global $wgMinervaEnableSiteNotice;
			$siteNoticeGenerator = new SiteNoticeGenerator(
				$this->userOptionsLookup,
				$this->jobQueueGroup,
			);
			return $siteNoticeGenerator->setNotice(
				'returnToHomepage',
				$siteNotice,
				$skin,
				$wgMinervaEnableSiteNotice
			);
		} else {
			$out->addModules( 'ext.guidedTour.tour.homepage_return' );
			return true;
		}
	}

	private static function isEditorOpen( WebRequest $request ): bool {
		return in_array( $request->getVal( 'action', 'view' ), [ 'edit', 'submit' ], true )
			|| $request->getCheck( 'veaction' );
	}

	private static function isSuggestedEditRequest( WebRequest $request ): bool {
		return $request->getBool( 'gesuggestededit' ) || $request->getCheck( 'geclickid' );
	}

	/**
	 * Register ResourceLoader modules which depend on other extensions.
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		if ( !self::growthTourDependenciesLoaded() ) {
			return;
		}
		$moduleTemplate = [
			'localBasePath' => dirname( __DIR__ ) . '/modules',
			'remoteExtPath' => 'GrowthExperiments/modules',
			'dependencies' => 'ext.guidedTour',
		];
		$modules = [
			'ext.guidedTour.tour.helppanel' => $moduleTemplate + [
				'packageFiles' => [
					'tours/helpPanelTour.js',
					'tours/tourUtils.js',
				],
				'messages' => [
					'growthexperiments-tour-helpdesk-response-tip-title',
					'growthexperiments-tour-response-tip-text',
					'growthexperiments-tour-response-button-okay',
				],
			],
			'ext.guidedTour.tour.homepage_mentor' => $moduleTemplate + [
				'packageFiles' => [
					'tours/homepageMentor.js',
					'tours/tourUtils.js',
				],
				'messages' => [
					'growthexperiments-tour-mentor-response-tip-personal-title',
					'growthexperiments-tour-mentor-response-tip-personal-text',
					'growthexperiments-tour-response-button-okay',
				],
			],
			'ext.guidedTour.tour.homepage_welcome' => $moduleTemplate + [
				'packageFiles' => [
					'tours/homepageWelcome.js',
					'tours/tourUtils.js',
				],
				'messages' => [
					'growthexperiments-tour-welcome-title',
					'growthexperiments-tour-welcome-description',
					'growthexperiments-tour-welcome-description-c',
					'growthexperiments-tour-welcome-description-d',
					'growthexperiments-tour-response-button-okay',
				],
			],
			'ext.guidedTour.tour.homepage_return' => $moduleTemplate + [
					'packageFiles' => [
						'tours/returnToHomepage.js',
						'tours/tourUtils.js',
					],
					'messages' => [
						'growthexperiments-tour-return-to-homepage',
						'growthexperiments-tour-response-button-okay',
					],
				],
			'ext.guidedTour.tour.homepage_discovery' => $moduleTemplate + [
				'packageFiles' => [
					'tours/homepageDiscovery.js',
					'tours/tourUtils.js',
				],
				'messages' => [
					'growthexperiments-tour-discovery-title',
					'growthexperiments-tour-discovery-description',
					'growthexperiments-tour-response-button-okay',
				],
			],
		];
		$resourceLoader->register( $modules );
	}

	public static function growthTourDependenciesLoaded(): bool {
		$extensionRegistry = ExtensionRegistry::getInstance();
		return $extensionRegistry->isLoaded( 'GuidedTour' ) &&
			   $extensionRegistry->isLoaded( 'Echo' ) &&
			   $extensionRegistry->isLoaded( 'EventLogging' );
	}

	/**
	 * Register tour state as hidden preferences
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		if ( !self::growthTourDependenciesLoaded() ) {
			return;
		}
		$preferences[self::TOUR_COMPLETED_HELP_PANEL] = [
			'type' => 'api',
		];
		if ( HomepageHooks::isHomepageEnabled() ) {
			$preferences[self::TOUR_COMPLETED_HOMEPAGE_MENTORSHIP] = [
				'type' => 'api',
			];
			$preferences[self::TOUR_COMPLETED_HOMEPAGE_WELCOME] = [
				'type' => 'api',
			];
			$preferences[self::TOUR_COMPLETED_HOMEPAGE_DISCOVERY] = [
				'type' => 'api',
			];
		}
	}

	/**
	 * Register default preferences for tours.
	 *
	 * Default is to set their visibility to true (seen), and in the LocalUserCreated
	 * hook we'll set these preferences back to false (unseen).
	 *
	 * @inheritDoc
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		if ( !self::growthTourDependenciesLoaded() ) {
			return;
		}
		$defaultOptions += [
			self::TOUR_COMPLETED_HELP_PANEL => true,
		];
		if ( HomepageHooks::isHomepageEnabled() ) {
			$defaultOptions += [
				self::TOUR_COMPLETED_HOMEPAGE_MENTORSHIP => true,
				self::TOUR_COMPLETED_HOMEPAGE_WELCOME => true,
				self::TOUR_COMPLETED_HOMEPAGE_DISCOVERY => true,
			];
		}
	}
}
