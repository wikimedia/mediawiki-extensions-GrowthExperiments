<?php

namespace GrowthExperiments;

use Config;
use ConfigException;
use GrowthExperiments\Config\GrowthConfigLoaderStaticTrait;
use GrowthExperiments\HelpPanel\HelpPanelButton;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use Html;
use MediaWiki\MediaWikiServices;
use MessageLocalizer;
use OOUI\Tag;
use OutputPage;
use RawMessage;
use Title;
use User;
use WebRequest;

class HelpPanel {
	use GrowthConfigLoaderStaticTrait;

	public const HELPDESK_QUESTION_TAG = 'help panel question';

	/**
	 * @return Tag
	 * @throws ConfigException
	 */
	public static function getHelpPanelCtaButton() {
		$helpdeskTitle = self::getHelpDeskTitle(
			GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )->getGrowthWikiConfig()
		);
		$btnWidgetArr = [
			'label' => wfMessage( 'growthexperiments-help-panel-cta-button-text' )->text(),
		];
		if ( $helpdeskTitle ) {
			$btnWidgetArr['href'] = $helpdeskTitle->getLinkURL();
		}
		return ( new Tag( 'div' ) )
			->addClasses( [ 'mw-ge-help-panel-cta' ] )
			->appendContent( new HelpPanelButton( $btnWidgetArr ) );
	}

	/**
	 * @param MessageLocalizer $ml
	 * @param Config $wikiConfig
	 * @return array Links that should appear in the help panel. Exported to JS as wgGEHelpPanelLinks.
	 */
	public static function getHelpPanelLinks(
		MessageLocalizer $ml,
		Config $wikiConfig
	) {
		if ( !self::isHelpPanelEnabled() ) {
			return [];
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$helpPanelLinks = Html::openElement( 'ul', [ 'class' => 'mw-ge-help-panel-links' ] );
		foreach ( $wikiConfig->get( 'GEHelpPanelLinks' ) as $link ) {
			$title = Title::newFromText( $link['title'] );
			if ( $title ) {
				$helpPanelLinks .= Html::rawElement(
					'li',
					[],
					$linkRenderer->makePreloadedLink( $title, $link['text'], '',
						[ 'target' => '_blank', 'data-link-id' => $link['id'] ?? '' ] )
				);
			}
		}
		$helpPanelLinks .= Html::closeElement( 'ul' );

		$helpDeskTitle = self::getHelpDeskTitle( $wikiConfig );
		$helpDeskLink = $helpDeskTitle ? $linkRenderer->makePreloadedLink(
			$helpDeskTitle,
			$ml->msg( 'growthexperiments-help-panel-community-help-desk-text' )->text(),
			'',
			[ 'target' => '_blank', 'data-link-id' => 'help-desk' ]
		) : null;

		$viewMoreTitle = Title::newFromText( $wikiConfig->get( 'GEHelpPanelViewMoreTitle' ) );
		$viewMoreLink = $viewMoreTitle ? $linkRenderer->makePreloadedLink(
			$viewMoreTitle,
			$ml->msg( 'growthexperiments-help-panel-editing-help-links-widget-view-more-link' )
				->text(),
			'',
			[ 'target' => '_blank', 'data-link-id' => 'view-more' ]
		) : null;

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
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		return $userOptionsLookup->getOption( $user, HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE );
	}

	/**
	 * Check if we should show help panel to user.
	 *
	 * @param OutputPage $out
	 * @param bool $checkAction
	 * @return bool
	 * @throws ConfigException
	 */
	public static function shouldShowHelpPanel( OutputPage $out, $checkAction = true ): bool {
		if ( !self::isHelpPanelEnabled() ) {
			return false;
		}

		if ( $out->getUser()->isAnon() ) {
			return false;
		}

		if ( in_array( $out->getTitle()->getNamespace(),
			self::getGrowthWikiConfig()->get( 'GEHelpPanelExcludedNamespaces' ) ) ) {
			return false;
		}
		if ( $checkAction ) {
			$action = $out->getRequest()->getVal( 'action', 'view' );
			if ( !in_array( $action, [ 'edit', 'submit' ] ) &&
				 !self::shouldShowForReadingMode( $out, $action ) ) {
				return false;
			}
		}
		if ( self::isSuggestedEditRequest( $out->getRequest() ) ) {
			return true;
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
		if ( $title->inNamespaces( NS_MAIN, NS_TALK ) &&
			SuggestedEdits::isGuidanceEnabled( $out->getContext() ) &&
			HomepageHooks::getClickId( $out->getContext() ) ) {
			return true;
		}
		return in_array( $title->getSubjectPage()->getNamespace(),
				   self::getGrowthWikiConfig()->get( 'GEHelpPanelReadingModeNamespaces' ) );
	}

	public static function isHelpPanelEnabled() {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHelpPanelEnabled' );
	}

	/**
	 * Get the help desk title and expand the templates and magic words it may contain
	 *
	 * @param Config $wikiConfig
	 * @return null|Title
	 * @throws ConfigException
	 */
	public static function getHelpDeskTitle( Config $wikiConfig ) {
		$helpdeskTitle = $wikiConfig->get( 'GEHelpPanelHelpDeskTitle' );
		if ( $helpdeskTitle === null ) {
			return null;
		}

		// RawMessage is used here to expand magic words like {{#time:o}} - see T213186, T224224
		$msg = new RawMessage( $helpdeskTitle );
		return Title::newFromText( $msg->inContentLanguage()->text() );
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

	/** Check if the request is from Special:Homepage to a Suggested Edit article.
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	private static function isSuggestedEditRequest( WebRequest $request ): bool {
		return $request->getBool( 'gesuggestededit' );
	}
}
