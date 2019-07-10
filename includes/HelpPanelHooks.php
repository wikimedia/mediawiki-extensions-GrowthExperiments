<?php

namespace GrowthExperiments;

use GrowthExperiments\HelpPanel\HelpPanelQuestionPoster;
use OutputPage;
use ResourceLoaderContext;
use RequestContext;
use Skin;
use User;

class HelpPanelHooks {

	const HELP_PANEL_PREFERENCES_TOGGLE = 'growthexperiments-help-panel-tog-help-panel';

	/**
	 * Register preference to toggle help panel.
	 *
	 * @param User $user
	 * @param array &$preferences Preferences object
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$preferences[self::HELP_PANEL_PREFERENCES_TOGGLE] = [
				'type' => 'toggle',
				'section' => 'editing/editor',
				'label-message' => self::HELP_PANEL_PREFERENCES_TOGGLE
			];
			$preferences[HelpPanelQuestionPoster::QUESTION_PREF] = [
				'type' => 'api',
			];
		}
	}

	/**
	 * Register default preferences for Help Panel.
	 *
	 * @param array &$wgDefaultUserOptions Reference to default options array
	 */
	public static function onUserGetDefaultOptions( &$wgDefaultUserOptions ) {
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$wgDefaultUserOptions += [
				self::HELP_PANEL_PREFERENCES_TOGGLE => false
			];
		}
	}

	/**
	 * LocalUserCreated hook handler.
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @throws \ConfigException
	 */
	public static function onLocalUserCreated( User $user, $autocreated ) {
		if ( !HelpPanel::isHelpPanelEnabled() ) {
			return;
		}

		// Enable the help panel for a percentage of non-autocreated users.
		$config = RequestContext::getMain()->getConfig();
		$enablePercentage = $config->get( 'GEHelpPanelNewAccountEnablePercentage' );
		if ( $user->isLoggedIn() && !$autocreated && rand( 0, 99 ) < $enablePercentage ) {
			$user->setOption( self::HELP_PANEL_PREFERENCES_TOGGLE, 1 );
			$user->saveSettings();
		}
	}

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @throws \ConfigException
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$definitelyShow = HelpPanel::shouldShowHelpPanel( $out );
		$maybeShow = HelpPanel::shouldShowHelpPanel( $out, false );

		if ( $definitelyShow ) {
			$out->enableOOUI();
			$out->addModuleStyles( 'ext.growthExperiments.HelpPanelCta.styles' );
			$out->addModuleStyles( 'ext.growthExperiments.HelpPanel.icons' );
			$out->addModules( 'ext.growthExperiments.HelpPanel' );

			$out->addHTML( HelpPanel::getHelpPanelCtaButton( Util::isMobile( $skin ) ) );
		}

		// If the help panel would be shown but for the value of the 'action' parameter,
		// add the email config var anyway. We'll need it if the user loads an editor via JS.
		// Also set wgGEHelpPanelEnabled to let our JS modules know it's safe to display the help panel.
		if ( $maybeShow ) {
			$out->addJsConfigVars( [
				// We know the help panel is enabled, otherwise we wouldn't get here
				'wgGEHelpPanelEnabled' => true,
			] + HelpPanel::getUserEmailConfigVars( $out->getUser() ) );

			if ( !$definitelyShow ) {
				// Add the init module to make sure that the main HelpPanel module is loaded
				// if and when VisualEditor is loaded
				$out->addModules( 'ext.growthExperiments.HelpPanel.init' );
			}
		}
	}

	/**
	 * Build the contents of the data.json file in the ext.growthExperiments.HelpPanel module.
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public static function getModuleData( ResourceLoaderContext $context ) {
		$config = $context->getConfig();
		$helpdeskTitle = HelpPanel::getHelpDeskTitle( $config );
		return [
			'GEHelpPanelLoggingEnabled' => $config->get( 'GEHelpPanelLoggingEnabled' ),
			'GEHelpPanelSearchEnabled' => $config->get( 'GEHelpPanelSearchEnabled' ),
			'GEHelpPanelSearchNamespaces' => $config->get( 'GEHelpPanelSearchNamespaces' ),
			'GEHelpPanelReadingModeNamespaces' => $config->get( 'GEHelpPanelReadingModeNamespaces' ),
			'GEHelpPanelSearchForeignAPI' => $config->get( 'GEHelpPanelSearchForeignAPI' ),
			'GEHelpPanelLinks' => HelpPanel::getHelpPanelLinks( $context, $config ),
			'GEHelpPanelHelpDeskTitle' => $helpdeskTitle ? $helpdeskTitle->getPrefixedText() : null
		];
	}

	/**
	 * ListDefinedTags and ChangeTagsListActive hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 *
	 * @param array &$tags The list of tags. Add your extension's tags to this array.
	 */
	public static function onListDefinedTags( &$tags ) {
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$tags[] = HelpPanel::HELP_PANEL_QUESTION_TAG;
		}
	}

}
