<?php

namespace GrowthExperiments;

use GrowthExperiments\Config\GrowthConfigLoaderStaticTrait;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\IMentorManager;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Cache\GenderCache;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MessageLocalizer;

class HelpPanelHooks implements
	GetPreferencesHook,
	UserGetDefaultOptionsHook,
	ResourceLoaderExcludeUserOptionsHook,
	LocalUserCreatedHook,
	BeforePageDisplayHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook
{
	use GrowthConfigLoaderStaticTrait;

	public const HELP_PANEL_PREFERENCES_TOGGLE = 'growthexperiments-help-panel-tog-help-panel';

	private Config $config;
	private Config $wikiConfig;
	private GenderCache $genderCache;
	private UserEditTracker $userEditTracker;
	private UserOptionsManager $userOptionsManager;
	private IMentorManager $mentorManager;
	private MentorStatusManager $mentorStatusManager;

	/**
	 * @param Config $config
	 * @param Config $wikiConfig
	 * @param GenderCache $genderCache
	 * @param UserEditTracker $userEditTracker
	 * @param UserOptionsManager $userOptionsManager
	 * @param IMentorManager $mentorManager
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		Config $config,
		Config $wikiConfig,
		GenderCache $genderCache,
		UserEditTracker $userEditTracker,
		UserOptionsManager $userOptionsManager,
		IMentorManager $mentorManager,
		MentorStatusManager $mentorStatusManager
	) {
		$this->config = $config;
		$this->wikiConfig = $wikiConfig;
		$this->genderCache = $genderCache;
		$this->userEditTracker = $userEditTracker;
		$this->userOptionsManager = $userOptionsManager;
		$this->mentorManager = $mentorManager;
		$this->mentorStatusManager = $mentorStatusManager;
	}

	/**
	 * Register preference to toggle help panel.
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[HelpdeskQuestionPoster::QUESTION_PREF] = [ 'type' => 'api', ];
	}

	/**
	 * Register default preferences for Help Panel.
	 *
	 * @param array &$defaultOptions
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			self::HELP_PANEL_PREFERENCES_TOGGLE => false
		];
	}

	/** @inheritDoc */
	public function onResourceLoaderExcludeUserOptions(
		array &$keysToExclude,
		RL\Context $context
	): void {
		$keysToExclude = array_merge( $keysToExclude, [
			HelpdeskQuestionPoster::QUESTION_PREF,
		] );
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $user->isTemp() ) {
			return;
		}

		// Enable the help panel for a percentage of non-autocreated users.
		if (
			$this->config->get( 'GEHelpPanelNewAccountEnableWithHomepage' ) &&
			HomepageHooks::isHomepageEnabled()
		) {
			// HomepageHooks::onLocalUserCreated() will enable the help panel if needed
			return;
		}

		$enablePercentage = $this->config->get( 'GEHelpPanelNewAccountEnablePercentage' );
		if ( !$autocreated && rand( 0, 99 ) < $enablePercentage ) {
			$this->userOptionsManager->setOption( $user, self::HELP_PANEL_PREFERENCES_TOGGLE, 1 );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		$maybeShow = HelpPanel::shouldShowHelpPanel( $out, false );
		if ( !$maybeShow ) {
			return;
		}

		$definitelyShow = HelpPanel::shouldShowHelpPanel( $out );

		if ( $definitelyShow ) {
			$out->enableOOUI();
			$out->addModuleStyles( 'ext.growthExperiments.HelpPanelCta.styles' );
			$out->addModuleStyles( 'ext.growthExperiments.icons' );
			$out->addModules( 'ext.growthExperiments.HelpPanel' );

			$out->addHTML( HelpPanel::getHelpPanelCtaButton( $this->wikiConfig ) );
		}

		// Note: wgGELinkRecommendationsFrontendEnabled reflects the configuration flag.
		// Checking whether Add Link has been disabled in community configuration is the
		// frontend code's responsibility.
		$out->addJsConfigVars( [
			'wgGEAskQuestionEnabled' => HelpPanel::getHelpDeskTitle( $this->wikiConfig ) !== null,
			'wgGELinkRecommendationsFrontendEnabled' =>
				$out->getConfig()->get( 'GELinkRecommendationsFrontendEnabled' )
		] );

		// If the help panel would be shown but for the value of the 'action' parameter,
		// add the email config var anyway. We'll need it if the user loads an editor via JS.
		$out->addJsConfigVars( [
				'wgGEHelpPanelMentorData' => $this->getMentorData(
					$this->wikiConfig,
					$out->getUser(),
					$out->getContext(),
					$out->getContext()->getLanguage()
				),
				// wgGEHelpPanelAskMentor needs to be here and not in getModuleData,
				// because getting current user is not possible within ResourceLoader context
				'wgGEHelpPanelAskMentor' =>
					$this->wikiConfig->get( 'GEMentorshipEnabled' ) &&
					$this->wikiConfig->get( 'GEHelpPanelAskMentor' ) &&
					$this->mentorManager->getMentorshipStateForUser(
						$out->getUser()
					) === IMentorManager::MENTORSHIP_ENABLED &&
					$this->mentorManager->getEffectiveMentorForUserSafe( $out->getUser() ) !== null,
			] + HelpPanel::getUserEmailConfigVars( $out->getUser() ) );

		if ( !$definitelyShow ) {
			// Add the init module to make sure that the main HelpPanel module is loaded
			// if and when VisualEditor is loaded
			$out->addModules( 'ext.growthExperiments.HelpPanel.init' );
		}
	}

	/**
	 * Build the contents of the data.json file in the ext.growthExperiments.Help module.
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function getModuleData( RL\Context $context, Config $config ) {
		$helpdeskTitle = HelpPanel::getHelpDeskTitle( self::getGrowthWikiConfig() );
		// The copyright warning can contain markup and has to be parsed via PHP messages API.
		$copyrightWarningMessage = $context->msg( 'wikimedia-copyrightwarning' );
		return [
			'GEHelpPanelSearchNamespaces' => self::getGrowthWikiConfig()
				->get( 'GEHelpPanelSearchNamespaces' ),
			'GEHelpPanelReadingModeNamespaces' => self::getGrowthWikiConfig()
				->get( 'GEHelpPanelReadingModeNamespaces' ),
			'GEHelpPanelSearchForeignAPI' => $config->get( 'GEHelpPanelSearchForeignAPI' ),
			'GEHelpPanelLinks' => HelpPanel::getHelpPanelLinks(
				$context, self::getGrowthWikiConfig()
			),
			'GEHelpPanelSuggestedEditsPreferredEditor' => self::getPreferredEditor( $context, $config ),
			'GEHelpPanelHelpDeskTitle' => $helpdeskTitle ? $helpdeskTitle->getPrefixedText() : null,
			'GEAskHelpCopyrightWarning' => $copyrightWarningMessage->exists() ?
				$copyrightWarningMessage->parse() : ''
		];
	}

	/** @inheritDoc */
	public function onListDefinedTags( &$tags ) {
		$tags[] = HelpPanel::HELPDESK_QUESTION_TAG;
	}

	/** @inheritDoc */
	public function onChangeTagsListActive( &$tags ) {
		$tags[] = HelpPanel::HELPDESK_QUESTION_TAG;
	}

	/**
	 * @param Config $wikiConfig
	 * @param User $user
	 * @param MessageLocalizer $localizer
	 * @param Language $language
	 * @return array
	 */
	private function getMentorData(
		Config $wikiConfig,
		User $user,
		MessageLocalizer $localizer,
		Language $language
	): array {
		if ( !$wikiConfig->get( 'GEHelpPanelAskMentor' ) || !$wikiConfig->get( 'GEMentorshipEnabled' ) ) {
			return [];
		}
		$mentor = $this->mentorManager->getMentorForUserSafe( $user );
		$effectiveMentor = $this->mentorManager->getEffectiveMentorForUserSafe( $user );

		if ( !$mentor || !$effectiveMentor ) {
			return [];
		}
		return [
			'name' => $mentor->getUserIdentity()->getName(),
			'gender' => $this->genderCache->getGenderOf(
				$mentor->getUserIdentity(),
				__METHOD__
			),
			'effectiveName' => $effectiveMentor->getUserIdentity()->getName(),
			'effectiveGender' => $this->genderCache->getGenderOf(
				$effectiveMentor->getUserIdentity(),
				__METHOD__
			),
			'editCount' => $this->userEditTracker->getUserEditCount( $mentor->getUserIdentity() ),
			'lastActive' => Mentorship::getMentorLastActive(
				$mentor->getUserIdentity(), $user,
				$localizer, $this->userEditTracker
			),
			'backAt' => $language->date(
				$this->mentorStatusManager->getMentorBackTimestamp( $mentor->getUserIdentity() ) ?? ''
			)
		];
	}

	/**
	 * Return preferred editor for each task type based on task type handler
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	private static function getPreferredEditor( RL\Context $context, Config $config ): array {
		$geServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );

		// Hack - RL\Context is not exposed to services initialization
		$validator = $geServices->getNewcomerTasksConfigurationValidator();
		$validator->setMessageLocalizer( $context );

		$taskTypes = $geServices->getNewcomerTasksConfigurationLoader()->getTaskTypes();
		$preferredEditorPerHandlerId = $config->get( 'GEHelpPanelSuggestedEditsPreferredEditor' );
		$preferredEditorPerTaskType = [];
		foreach ( $taskTypes as $taskTypeId => $taskType ) {
			$preferredEditorPerTaskType[ $taskTypeId ] = $preferredEditorPerHandlerId[ $taskType->getHandlerId() ];
		}
		return $preferredEditorPerTaskType;
	}

}
