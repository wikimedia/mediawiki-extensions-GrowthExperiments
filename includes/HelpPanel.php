<?php

namespace GrowthExperiments;

use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use Config;
use Html;
use MessageLocalizer;
use OutputPage;
use Title;
use User;

class HelpPanel {

	const HELP_PANEL_QUESTION_TAG = 'help panel question';

	/**
	 * @return ButtonWidget
	 * @throws \ConfigException
	 */
	public static function getHelpPanelCtaButton() {
		return new ButtonWidget( [
			'classes' => [ 'mw-ge-help-panel-cta' ],
			'id' => 'mw-ge-help-panel-cta',
			'href' => Title::newFromText(
				MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHelpPanelHelpDeskTitle' )
			)->getLinkURL(),
			'label' => wfMessage( 'growthexperiments-help-panel-cta-button-text' )->text(),
			'infusable' => true,
			'icon' => 'helpNotice',
			'flags' => [ 'primary', 'progressive' ],
		] );
	}

	/**
	 * @param MessageLocalizer $ml
	 * @param Config $config
	 * @return array Links that should appear in the help panel. Exported to JS as wgGEHelpPanelLinks.
	 */
	public static function getHelpPanelLinks( MessageLocalizer $ml, Config $config ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$helpPanelLinks = Html::openElement( 'ul' );
		foreach ( $config->get( 'GEHelpPanelLinks' ) as $link ) {
			$title = Title::newFromText( $link['title'] );
			if ( $title ) {
				$helpPanelLinks .= Html::rawElement(
					'li',
					[],
					$linkRenderer->makeLink( $title, $link['text'], [ 'target' => '_blank' ] )
				);
			}
		}
		$helpPanelLinks .= Html::closeElement( 'ul' );

		$helpDeskTitle = Title::newFromText( $config->get( 'GEHelpPanelHelpDeskTitle' ) );
		$helpDeskLink = $linkRenderer->makeLink( $helpDeskTitle, null, [ 'target' => '_blank' ] );

		$viewMoreTitle = Title::newFromText( $config->get( 'GEHelpPanelViewMoreTitle' ) );
		$viewMoreLink = $linkRenderer->makeLink(
			$viewMoreTitle,
			$ml->msg( 'growthexperiments-help-panel-editing-help-links-widget-view-more-link' )->text(),
			[ 'target' => '_blank' ]
		);

		return [
			'helpPanelLinks' => $helpPanelLinks,
			'helpDeskLink' => $helpDeskLink,
			'viewMoreLink' => $viewMoreLink
		];
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
