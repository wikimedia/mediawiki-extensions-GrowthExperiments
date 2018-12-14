<?php

namespace GrowthExperiments;

use OutputPage;
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
		}
	}

	/**
	 * Register default preferences for Help Panel.
	 *
	 * @param array &$wgDefaultUserOptions Reference to default options array
	 */
	public static function onUserGetDefaultOptions( &$wgDefaultUserOptions ) {
		$wgDefaultUserOptions += [
			self::HELP_PANEL_PREFERENCES_TOGGLE => false
		];
	}

	/**
	 * LocalUserCreated hook handler.
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @throws \ConfigException
	 */
	public static function onLocalUserCreated( User $user, $autocreated ) {
		// Enable the help panel for 50% of non-autocreated users.
		$config = RequestContext::getMain()->getConfig();
		$enableProportion = $config->get( 'GEHelpPanelNewAccountEnableProportion' );
		if ( $user->isAnon() || $autocreated || !$enableProportion ) {
			return;
		}
		if ( ( $user->getId() % $enableProportion ) === 0 ) {
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
			$out->addModules( 'ext.growthExperiments.HelpPanel' );

			$out->addHTML( HelpPanel::getHelpPanelCtaButton() );
		}

		// If the help panel would be shown but for the value of the 'action' parameter,
		// add the email config var anyway. We'll need it if the user loads an editor via JS.
		// Also set wgGEHelpPanelEnabled to let our JS modules know it's safe to display the help panel.
		if ( $maybeShow ) {
			$out->addJsConfigVars( [
				// We know the help panel is enabled, otherwise we wouldn't get here
				'wgGEHelpPanelEnabled' => true,
				'wgGEHelpPanelLoggingEnabled' => $out->getConfig()->get( 'GEHelpPanelLoggingEnabled' ),
				'wgGEHelpPanelUserEmail' => $out->getUser()->getEmail(),
				'wgGEHelpPanelUserEmailConfirmed' => $out->getUser()->isEmailConfirmed()
			] );

			if ( !$definitelyShow ) {
				// Add the init module to make sure that the main HelpPanel module is loaded
				// if and when VisualEditor is loaded
				$out->addModules( 'ext.growthExperiments.HelpPanel.init' );
			}
		}
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
		$tags[] = HelpPanel::HELP_PANEL_QUESTION_TAG;
	}

}
