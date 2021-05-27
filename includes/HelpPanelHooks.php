<?php

namespace GrowthExperiments;

use Config;
use GrowthExperiments\Config\GrowthConfigLoaderStaticTrait;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use MediaWiki\MediaWikiServices;
use MessageLocalizer;
use OutputPage;
use RequestContext;
use ResourceLoaderContext;
use Skin;
use User;

class HelpPanelHooks {
	use GrowthConfigLoaderStaticTrait;

	public const HELP_PANEL_PREFERENCES_TOGGLE = 'growthexperiments-help-panel-tog-help-panel';

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
			$preferences[HelpdeskQuestionPoster::QUESTION_PREF] = [
				'type' => 'api',
			];
		}

		$context = RequestContext::getMain();
		// FIXME: Guidance doesn't need an opt-in anymore, let's remove this.
		if ( SuggestedEdits::isGuidanceEnabledForAnyone( $context )
			&& $context->getConfig()->get( 'GENewcomerTasksGuidanceRequiresOptIn' )
		) {
			$preferences[SuggestedEdits::GUIDANCE_ENABLED_PREF] = [
				'type' => 'api',
			];
		}
	}

	/**
	 * Register default preferences for Help Panel.
	 *
	 * @param array &$defaultOptions
	 */
	public static function onUserGetDefaultOptions( &$defaultOptions ) {
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$defaultOptions += [
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
		if (
			$config->get( 'GEHelpPanelNewAccountEnableWithHomepage' ) &&
			HomepageHooks::isHomepageEnabled()
		) {
			// HomepageHooks::onLocalUserCreated() will enable the help panel if needed
			return;
		}

		$enablePercentage = $config->get( 'GEHelpPanelNewAccountEnablePercentage' );
		if ( $user->isRegistered() && !$autocreated && rand( 0, 99 ) < $enablePercentage ) {
			$user->setOption( self::HELP_PANEL_PREFERENCES_TOGGLE, 1 );
		}
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @throws \ConfigException
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$geServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$wikiConfig = $geServices->getGrowthWikiConfig();

		$definitelyShow = HelpPanel::shouldShowHelpPanel( $out );
		$maybeShow = HelpPanel::shouldShowHelpPanel( $out, false );

		if ( $definitelyShow ) {
			$out->enableOOUI();
			$out->addModuleStyles( 'ext.growthExperiments.HelpPanelCta.styles' );
			$out->addModuleStyles( 'ext.growthExperiments.HelpPanel.icons' );
			$out->addModules( 'ext.growthExperiments.HelpPanel' );

			$out->addHTML( HelpPanel::getHelpPanelCtaButton( Util::isMobile( $skin ) ) );
		}

		if ( SuggestedEdits::isGuidanceEnabled( $out->getContext() ) ) {
			$out->addJsConfigVars( [
				'wgGENewcomerTasksGuidanceEnabled' => true,
				'wgGEAskQuestionEnabled' => HelpPanel::getHelpDeskTitle( $wikiConfig ) !== null,
				'wgGELinkRecommendationsFrontendEnabled' =>
					$out->getConfig()->get( 'GELinkRecommendationsFrontendEnabled' )
			] );
		}

		// If the help panel would be shown but for the value of the 'action' parameter,
		// add the email config var anyway. We'll need it if the user loads an editor via JS.
		// Also set wgGEHelpPanelEnabled to let our JS modules know it's safe to display the help panel.
		if ( $maybeShow ) {
			$out->addJsConfigVars( [
				// We know the help panel is enabled, otherwise we wouldn't get here
				'wgGEHelpPanelEnabled' => true,
				'wgGEHelpPanelMentorData'
					=> self::getMentorData( $wikiConfig, $out->getUser(), $out->getContext() ),
				// wgGEHelpPanelAskMentor needs to be here and not in getModuleData,
				// because getting current user is not possible within ResourceLoader context
				'wgGEHelpPanelAskMentor' =>
					$wikiConfig->get( 'GEMentorshipEnabled' ) &&
					$wikiConfig->get( 'GEHelpPanelAskMentor' ) &&
					$geServices->getMentorManager()->getMentorForUserSafe( $out->getUser() ) !== null,
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
	 * @param Config $config
	 * @return array
	 */
	public static function getModuleData( ResourceLoaderContext $context, Config $config ) {
		$helpdeskTitle = HelpPanel::getHelpDeskTitle( self::getGrowthWikiConfig() );
		return [
			'GEHelpPanelLoggingEnabled' => $config->get( 'GEHelpPanelLoggingEnabled' ),
			'GEHelpPanelSearchNamespaces' => self::getGrowthWikiConfig()
				->get( 'GEHelpPanelSearchNamespaces' ),
			'GEHelpPanelReadingModeNamespaces' => self::getGrowthWikiConfig()
				->get( 'GEHelpPanelReadingModeNamespaces' ),
			'GEHelpPanelSearchForeignAPI' => $config->get( 'GEHelpPanelSearchForeignAPI' ),
			'GEHelpPanelLinks' => HelpPanel::getHelpPanelLinks(
				$context, self::getGrowthWikiConfig()
			),
			'GEHelpPanelSuggestedEditsPreferredEditor' => self::getPreferredEditor( $context ),
			'GEHelpPanelHelpDeskTitle' => $helpdeskTitle ? $helpdeskTitle->getPrefixedText() : null,
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
			$tags[] = HelpPanel::HELPDESK_QUESTION_TAG;
		}
	}

	private static function getMentorData(
		Config $wikiConfig,
		User $user,
		MessageLocalizer $localizer
	) {
		if ( !$wikiConfig->get( 'GEHelpPanelAskMentor' ) || !$wikiConfig->get( 'GEMentorshipEnabled' ) ) {
			return [];
		}
		$mentor = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )
			->getMentorManager()->getMentorForUserSafe( $user );
		if ( !$mentor ) {
			return [];
		}
		return [
			'name' => $mentor->getMentorUser()->getName(),
			'editCount' => MediaWikiServices::getInstance()->getUserEditTracker()
				->getUserEditCount( $mentor->getMentorUser() ),
			'lastActive' => Mentorship::getMentorLastActive( $mentor->getMentorUser(), $user, $localizer ),
		];
	}

	/**
	 * Return preferred editor for each task type based on task type handler
	 *
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	private static function getPreferredEditor( ResourceLoaderContext $context ): array {
		$geServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );

		// Hack - ResourceLoaderContext is not exposed to services initialization
		$validator = $geServices->getNewcomerTasksConfigurationValidator();
		$validator->setMessageLocalizer( $context );

		$taskTypes = $geServices->getNewcomerTasksConfigurationLoader()->getTaskTypes();
		$preferredEditorPerHandlerId = self::getGrowthWikiConfig()->get( 'GEHelpPanelSuggestedEditsPreferredEditor' );
		$preferredEditorPerTaskType = [];
		foreach ( $taskTypes as $taskTypeId => $taskType ) {
			$preferredEditorPerTaskType[ $taskTypeId ] = $preferredEditorPerHandlerId[ $taskType->getHandlerId() ];
		}
		return $preferredEditorPerTaskType;
	}

}
