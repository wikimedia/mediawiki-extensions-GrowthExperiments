<?php

namespace GrowthExperiments;

use ResourceLoader;
use User;

use ConfigException;

class TourHooks {

	const TOUR_COMPLETED_HELP_PANEL = 'growthexperiments-tour-help-panel';
	const TOUR_COMPLETED_HOMEPAGE_MENTORSHIP = 'growthexperiments-tour-homepage-mentorship';
	const TOUR_COMPLETED_HOMEPAGE_WELCOME = 'growthexperiments-tour-homepage-welcome';
	const TOUR_COMPLETED_HOMEPAGE_DISCOVERY = 'growthexperiments-tour-homepage-discovery';

	/**
	 * @param \OutputPage &$out
	 * @param \Skin &$skin
	 */
	public static function onBeforePageDisplay( \OutputPage &$out, \Skin &$skin ) {
		// Show the discovery tour if the user isn't on WelcomeSurvey or Homepage.
		// If they have already seen the welcome tour, don't show the discovery one.
		if ( !$out->getTitle()->isSpecial( 'WelcomeSurvey' ) &&
			 !$out->getTitle()->isSpecial( 'Homepage' ) &&
			 HomepageHooks::isHomepageEnabled( $out->getUser() ) &&
			 !$out->getUser()->getBoolOption( self::TOUR_COMPLETED_HOMEPAGE_WELCOME ) ) {
			Util::maybeAddGuidedTour(
				$out,
				self::TOUR_COMPLETED_HOMEPAGE_DISCOVERY,
				'ext.guidedTour.tour.homepage_discovery'
			);
		}
	}

	/**
	 * Register ResourceLoader modules with dynamic dependencies.
	 *
	 * @param ResourceLoader $resourceLoader
	 * @throws \MWException
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		if ( !self::growthTourDependenciesLoaded() ) {
			return;
		}
		$moduleTemplate = [
			'localBasePath' => dirname( __DIR__ ) . '/modules',
			'remoteExtPath' => 'GrowthExperiments/modules',
			'dependencies' => 'ext.guidedTour',
			'targets' => [ 'desktop' ]
		];
		$modules = [
			'ext.guidedTour.tour.helppanel' => $moduleTemplate + [
				'scripts' => 'help/ext.growthExperiments.helppanel.tour.js',
				'messages' => [
					'growthexperiments-tour-helpdesk-response-tip-title',
					'growthexperiments-tour-response-tip-text',
					'growthexperiments-tour-response-button-okay'
				],
			],
			'ext.guidedTour.tour.homepage_mentor' => $moduleTemplate + [
				'scripts' => 'help/ext.growthExperiments.homepage.mentor.tour.js',
				'messages' => [
					'growthexperiments-tour-mentor-response-tip-personal-title',
					'growthexperiments-tour-mentor-response-tip-personal-text',
					'growthexperiments-tour-response-button-okay'
				],
			],
			'ext.guidedTour.tour.homepage_welcome' => $moduleTemplate + [
				'scripts' => [ 'homepage/ext.growthExperiments.homepage.welcome.tour.js' ],
				'messages' => [
					'growthexperiments-tour-welcome-title',
					'growthexperiments-tour-welcome-description',
					'growthexperiments-tour-response-button-okay'
				],
			],
			'ext.guidedTour.tour.homepage_discovery' => $moduleTemplate + [
				'scripts' => [ 'homepage/ext.growthExperiments.homepage.discovery.tour.js' ],
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
		$extensionRegistry = \ExtensionRegistry::getInstance();
		return $extensionRegistry->isLoaded( 'GuidedTour' ) &&
			   $extensionRegistry->isLoaded( 'Echo' ) &&
			   $extensionRegistry->isLoaded( 'EventLogging' );
	}

	/**
	 * Register tour state as hidden preferences
	 *
	 * @param User $user
	 * @param array &$preferences Preferences object
	 * @throws ConfigException
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		if ( !self::growthTourDependenciesLoaded() ) {
			return;
		}
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$preferences[self::TOUR_COMPLETED_HELP_PANEL] = [
				'type' => 'api',
			];
		}
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
	 * @param array &$wgDefaultUserOptions Reference to default options array
	 * @throws ConfigException
	 */
	public static function onUserGetDefaultOptions( &$wgDefaultUserOptions ) {
		if ( !self::growthTourDependenciesLoaded() ) {
			return;
		}
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$wgDefaultUserOptions += [
				self::TOUR_COMPLETED_HELP_PANEL => true
			];
		}
		if ( HomepageHooks::isHomepageEnabled() ) {
			$wgDefaultUserOptions += [
				self::TOUR_COMPLETED_HOMEPAGE_MENTORSHIP => true,
				self::TOUR_COMPLETED_HOMEPAGE_WELCOME => true,
				self::TOUR_COMPLETED_HOMEPAGE_DISCOVERY => true,
			];
		}
	}
}
