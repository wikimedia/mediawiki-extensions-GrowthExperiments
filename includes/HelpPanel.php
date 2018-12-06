<?php

namespace GrowthExperiments;

use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use OutputPage;
use Title;
use User;

class HelpPanel {

	/**
	 * @return ButtonWidget
	 * @throws \ConfigException
	 */
	public static function getHelpPanelCtaButton() {
		return new ButtonWidget( [
			'classes' => [ 'mw-ge-help-panel-cta' ],
			'id' => 'mw-ge-help-panel-cta',
			'href' => Title::newFromText(
				MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHelpPanelHelpDeskTitle'
				)
			)->getLinkURL(),
			'label' => wfMessage( 'growthexperiments-help-panel-cta-button-text' )->text(),
			'infusable' => true,
			'icon' => 'helpNotice',
			'flags' => [ 'primary', 'progressive' ],
		] );
	}

	/**
	 * Whether to show the help panel to a particular user.
	 *
	 * @param User $user
	 * @return bool
	 */
	private static function shouldShowHelpPanelToUser( User $user ) {
		return !$user->isAnon() &&
			   $user->getOption( HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE );
	}

	/**
	 * Check if we should show help panel to user.
	 *
	 * @param OutputPage $out
	 * @param bool $checkAction
	 * @return bool
	 * @throws \ConfigException
	 */
	public static function shouldShowHelpPanel( OutputPage $out, $checkAction = true ) {
		if ( !self::isHelpPanelEnabled() ) {
			return false;
		}
		if ( in_array( $out->getTitle()->getNamespace(),
			$out->getConfig()->get( 'GEHelpPanelExcludedNamespaces' ) ) ) {
			return false;
		}
		// Ensure the help desk title is valid.
		$helpDeskTitle = Title::newFromText( $out->getConfig()->get( 'GEHelpPanelHelpDeskTitle' ) );
		if ( !$helpDeskTitle || !$helpDeskTitle->exists() ) {
			return false;
		}
		if ( $checkAction ) {
			$action = $out->getRequest()->getVal( 'action', 'view' );
			if ( !in_array( $action, [ 'edit', 'submit' ] ) ) {
				return false;
			}
		}
		return self::shouldShowHelpPanelToUser( $out->getUser() );
	}

	public static function isHelpPanelEnabled() {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHelpPanelEnabled' );
	}
}
