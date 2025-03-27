<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;

class TourHooks implements
	BeforePageDisplayHook,
	ResourceLoaderRegisterModulesHook,
	GetPreferencesHook,
	UserGetDefaultOptionsHook
{

	public const TOUR_COMPLETED_HELP_PANEL = 'growthexperiments-tour-help-panel';
	public const TOUR_COMPLETED_HOMEPAGE_MENTORSHIP = 'growthexperiments-tour-homepage-mentorship';
	public const TOUR_COMPLETED_HOMEPAGE_WELCOME = 'growthexperiments-tour-homepage-welcome';
	public const TOUR_COMPLETED_HOMEPAGE_DISCOVERY = 'growthexperiments-tour-homepage-discovery';

	private UserOptionsLookup $userOptionsLookup;
	private ExperimentUserManager $experimentUserManager;
	private Config $config;
	private UserOptionsManager $userOptionsManager;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param ExperimentUserManager $experimentUserManager
	 * @param Config $config
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		ExperimentUserManager $experimentUserManager,
		Config $config,
		UserOptionsManager $userOptionsManager
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->experimentUserManager = $experimentUserManager;
		$this->config = $config;
		$this->userOptionsManager = $userOptionsManager;
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
			'dependencies' => 'ext.guidedTour'
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
					'growthexperiments-tour-response-button-okay'
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
					'growthexperiments-tour-response-button-okay'
				],
			],
			'ext.guidedTour.tour.homepage_welcome' => $moduleTemplate + [
				'packageFiles' => [
					'tours/homepageWelcome.js',
					'tours/tourUtils.js',
					"ext.growthExperiments.Homepage.Logger/index.js",
					"utils/Utils.js"
				],
				'messages' => [
					'growthexperiments-tour-welcome-title',
					'growthexperiments-tour-welcome-description',
					'growthexperiments-tour-welcome-description-c',
					'growthexperiments-tour-welcome-description-d',
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
					'growthexperiments-tour-response-button-okay'
				]
			],
		];
		$resourceLoader->register( $modules );
	}

	/**
	 * @return bool
	 */
	public static function growthTourDependenciesLoaded() {
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
			self::TOUR_COMPLETED_HELP_PANEL => true
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
