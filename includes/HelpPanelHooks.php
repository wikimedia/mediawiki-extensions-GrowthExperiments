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
		if ( HelpPanel::shouldShowHelpPanel( $out ) ) {
			$out->enableOOUI();
			$out->addModuleStyles( 'ext.growthExperiments.HelpPanelCta.styles' );
			$out->addModules( 'ext.growthExperiments.HelpPanel' );

			$out->addHTML( HelpPanel::getHelpPanelCtaButton() );
		}

		// If the help panel would be shown but for the value of the 'action' parameter,
		// add the email config var anyway. We'll need it if the user loads an editor via JS.
		if ( HelpPanel::shouldShowHelpPanel( $out, false ) ) {
			$out->addJsConfigVars( [
				'wgGEHelpPanelUserEmail' => $out->getUser()->getEmail(),
				'wgGEHelpPanelUserEmailConfirmed' => $out->getUser()->isEmailConfirmed()
			] );
		}
	}

}
