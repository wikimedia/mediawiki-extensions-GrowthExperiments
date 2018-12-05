<?php

namespace GrowthExperiments;

use Html;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RequestContext;
use Skin;
use Title;
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
		if ( !HelpPanel::shouldShowHelpPanel( $out ) ) {
			return;
		}
		$out->enableOOUI();
		$out->addModuleStyles( 'ext.growthExperiments.HelpPanelCta.styles' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$helpPanelLinks = Html::openElement( 'ul' );
		foreach ( $out->getConfig()->get( 'GEHelpPanelLinks' ) as $link ) {
			$title = Title::newFromText( $link['title'] );
			$helpPanelLinks .= Html::openElement( 'li' );
			$helpPanelLinks .= $linkRenderer->makeLink( $title, $link['text'], [ 'target' => '_blank' ] );
			$helpPanelLinks .= Html::closeElement( 'li' );
		}
		$helpPanelLinks .= Html::closeElement( 'ul' );
		$title = Title::newFromText( $out->getConfig()->get( 'GEHelpPanelHelpDeskTitle' ) );
		$helpPanelHelpDeskLink = $linkRenderer->makeLink(
			$title, null, [ 'target' => '_blank' ]
		);
		$title = Title::newFromText( $out->getConfig()->get( 'GEHelpPanelViewMoreTitle' ) );
		$wgGEHelpPanelViewMore = $linkRenderer->makeLink( $title,
			wfMessage( 'growthexperiments-help-panel-editing-help-links-widget-view-more-link' )
				->inContentLanguage()
				->text() );
		$out->addJsConfigVars( [
			'wgGEHelpPanelLinks' => $helpPanelLinks,
			'wgGEHelpPanelHelpDeskLink' => $helpPanelHelpDeskLink,
			'wgGEHelpPanelViewMore' => $wgGEHelpPanelViewMore,
			'wgGEHelpPanelEmail' => $out->getUser()->getEmail(),
			'wgGEHelpPanelHelpDeskTitle' => $out->getConfig()->get( 'GEHelpPanelHelpDeskTitle' )
		] );
		$out->addModules( [
			'ext.growthExperiments.HelpPanel'
		] );
		$out->addHTML( HelpPanel::getHelpPanelCtaButton() );
	}

}
