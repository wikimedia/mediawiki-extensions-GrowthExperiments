<?php

namespace GrowthExperiments;

use ConfigException;
use MediaWiki\MediaWikiServices;
use MessageCache;
use OOUI\ButtonWidget;
use Config;
use Html;
use MessageLocalizer;
use OutputPage;
use OOUI\Tag;
use Title;
use User;

class HelpPanel {

	const HELP_PANEL_QUESTION_TAG = 'help panel question';

	/**
	 * @param bool $mobile
	 * @return Tag
	 * @throws ConfigException
	 */
	public static function getHelpPanelCtaButton( $mobile ) {
		return ( new Tag( 'div' ) )
			->addClasses( [ 'mw-ge-help-panel-cta', $mobile ? 'mw-ge-help-panel-cta-mobile' : '' ] )
			->appendContent( new ButtonWidget( [
				'id' => 'mw-ge-help-panel-cta-button',
				'href' => self::getHelpDeskTitle(
					MediaWikiServices::getInstance()->getMainConfig()
				)->getLinkURL(),
				'target' => '_blank',
				'label' => $mobile ? '' : wfMessage( 'growthexperiments-help-panel-cta-button-text' )->text(),
				'infusable' => true,
				'icon' => 'askQuestion',
				'flags' => [ 'primary', 'progressive' ],
			] ) );
	}

	/**
	 * @param MessageLocalizer $ml
	 * @param Config $config
	 * @return array Links that should appear in the help panel. Exported to JS as wgGEHelpPanelLinks.
	 * @throws ConfigException
	 */
	public static function getHelpPanelLinks( MessageLocalizer $ml, Config $config ) {
		if ( !self::isHelpPanelEnabled() ) {
			return [];
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$helpPanelLinks = Html::openElement( 'ul' );
		foreach ( $config->get( 'GEHelpPanelLinks' ) as $link ) {
			$title = Title::newFromText( $link['title'] );
			if ( $title ) {
				$helpPanelLinks .= Html::rawElement(
					'li',
					[],
					$linkRenderer->makeLink( $title, $link['text'],
						[ 'target' => '_blank', 'data-link-id' => $link['id'] ?? '' ] )
				);
			}
		}
		$helpPanelLinks .= Html::closeElement( 'ul' );

		$helpDeskTitle = self::getHelpDeskTitle( $config );
		$helpDeskLink = $linkRenderer->makeLink(
			$helpDeskTitle,
			$ml->msg( 'growthexperiments-help-panel-community-help-desk-text' )->text(),
			[ 'target' => '_blank', 'data-link-id' => 'help-desk' ]
		);

		$viewMoreTitle = Title::newFromText( $config->get( 'GEHelpPanelViewMoreTitle' ) );
		$viewMoreLink = $linkRenderer->makeLink(
			$viewMoreTitle,
			$ml->msg( 'growthexperiments-help-panel-editing-help-links-widget-view-more-link' )
				->text(),
			[ 'target' => '_blank', 'data-link-id' => 'view-more' ]
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
	 * @throws ConfigException
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
		$helpDeskTitle = self::getHelpDeskTitle( $out->getConfig() );
		if ( !$helpDeskTitle || !$helpDeskTitle->exists() ) {
			return false;
		}
		if ( $checkAction ) {
			$action = $out->getRequest()->getVal( 'action', 'view' );
			if ( !in_array( $action, [ 'edit', 'submit' ] ) &&
				 !self::shouldShowForReadingMode( $out, $action ) ) {
				return false;
			}
		}
		return self::shouldShowHelpPanelToUser( $out->getUser() );
	}

	/**
	 * If action=view, check if we are in allowed namespace.
	 *
	 * Note that views to talk titles will perform a look up for the subject title namespace,
	 * so specifying 4 (NS_PROJECT) as a namespace for which to enable help panel reading mode
	 * will also result in enabling 5 (NS_PROJECT_TALK) as an additional namespace.
	 *
	 * @param OutputPage $out
	 * @param string $action
	 * @return bool
	 * @throws ConfigException
	 */
	public static function shouldShowForReadingMode( OutputPage $out, $action ) {
		if ( $action !== 'view' ) {
			return false;
		}
		$title = $out->getTitle();
		if ( !$title ) {
			return false;
		}
		if ( $title->isMainPage() ) {
			// kowiki uses a Wikipedia namespace page as its Main_Page.
			return false;
		}
		return in_array( $title->getSubjectPage()->getNamespace(),
				   $out->getConfig()->get( 'GEHelpPanelReadingModeNamespaces' ) );
	}

	public static function isHelpPanelEnabled() {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHelpPanelEnabled' );
	}

	/**
	 * Get the help desk title and expand the templates and magic words it may contain
	 *
	 * @param Config $config
	 * @return null|Title
	 * @throws ConfigException
	 */
	public static function getHelpDeskTitle( $config ) {
		return Title::newFromText(
			MessageCache::singleton()->transform(
				$config->get( 'GEHelpPanelHelpDeskTitle' )
			)
		);
	}

	/**
	 * Get the config vars needed to properly display the user email status
	 * in the question poster dialog used in the Help Panel as well as the
	 * Help and Mentorship modules.
	 *
	 * @param User $user
	 * @return array
	 */
	public static function getUserEmailConfigVars( User $user ) {
		return [
			'wgGEHelpPanelUserEmail' => $user->getEmail(),
			'wgGEHelpPanelUserEmailConfirmed' => $user->isEmailConfirmed(),
		];
	}
}
