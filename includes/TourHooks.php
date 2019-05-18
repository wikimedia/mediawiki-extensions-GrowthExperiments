<?php

namespace GrowthExperiments;

use ResourceLoader;
use User;

use ConfigException;

class TourHooks {

	const TOUR_COMPLETED_HELP_PANEL = 'growthexperiments-tour-help-panel';
	const TOUR_COMPLETED_HOMEPAGE_MENTORSHIP = 'growthexperiments-tour-homepage-mentorship';
	const TOUR_COMPLETED_HOMEPAGE_HELP = 'growthexperiments-tour-homepage-help';

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
			'ext.guidedTour.tour.helpdesk' => $moduleTemplate + [
				'scripts' => 'help/ext.growthExperiments.helpdesk.js',
				'messages' => [
					'growthexperiments-tour-helpdesk-response-tip-title',
					'growthexperiments-tour-response-tip-text',
					'growthexperiments-tour-response-button-okay'
				],
			],
			'ext.guidedTour.tour.mentor' => $moduleTemplate + [
				'scripts' => 'help/ext.growthExperiments.mentor.js',
				'messages' => [
					'growthexperiments-tour-mentor-response-tip-personal-title',
					'growthexperiments-tour-mentor-response-tip-personal-text',
					'growthexperiments-tour-response-button-okay'
				],
			]
		];
		$resourceLoader->register( $modules );
	}

	/**
	 * @return bool
	 */
	private static function growthTourDependenciesLoaded() {
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
			$preferences[self::TOUR_COMPLETED_HOMEPAGE_HELP] = [
				'type' => 'api',
			];
		}
	}

	/**
	 * Register default preferences for tours.
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
				self::TOUR_COMPLETED_HELP_PANEL => false
			];
		}
		if ( HomepageHooks::isHomepageEnabled() ) {
			$wgDefaultUserOptions += [
				self::TOUR_COMPLETED_HOMEPAGE_HELP => false,
				self::TOUR_COMPLETED_HOMEPAGE_MENTORSHIP => false
			];
		}
	}
}
