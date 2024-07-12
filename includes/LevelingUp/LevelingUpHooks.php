<?php

namespace GrowthExperiments\LevelingUp;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\VariantHooks;
use GrowthExperiments\VisualEditorHooks;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\UserLocator;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\UserIdentity;

/**
 * Hooks for the "leveling up" feature.
 * @see https://www.mediawiki.org/wiki/Growth/Positive_reinforcement#Leveling_up
 */
class LevelingUpHooks implements
	BeforePageDisplayHook,
	VisualEditorApiVisualEditorEditPostSaveHook,
	UserGetDefaultOptionsHook
{

	private Config $config;
	private ConfigurationLoader $configurationLoader;
	private ExperimentUserManager $experimentUserManager;
	private LevelingUpManager $levelingUpManager;

	/**
	 * @param Config $config
	 * @param ConfigurationLoader $configurationLoader
	 * @param ExperimentUserManager $experimentUserManager
	 * @param LevelingUpManager $levelingUpManager
	 */
	public function __construct(
		Config $config,
		ConfigurationLoader $configurationLoader,
		ExperimentUserManager $experimentUserManager,
		LevelingUpManager $levelingUpManager
	) {
		$this->config = $config;
		$this->configurationLoader = $configurationLoader;
		$this->experimentUserManager = $experimentUserManager;
		$this->levelingUpManager = $levelingUpManager;
	}

	/**
	 * Load the InviteToSuggestedEdits module after a VisualEditor save.
	 * @inheritDoc
	 */
	public function onVisualEditorApiVisualEditorEditPostSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array $params,
		array $pluginData,
		array $saveResult,
		array &$apiResponse
	): void {
		$taskTypes = $this->configurationLoader->getTaskTypes();
		$pluginFields = array_map(
			fn ( $taskTypeId ) => VisualEditorHooks::PLUGIN_PREFIX . $taskTypeId,
			array_keys( $taskTypes )
		);
		$isSuggestedEdit = count( array_intersect( $pluginFields, array_keys( $pluginData ) ) ) > 0;

		// Check that the feature is enabled, we are editing an article and the user
		// just passed the threshold for the invite.
		// Also check if the current edit is a suggested edit, as a micro-optimisation
		// (shouldInviteUserAfterNormalEdit() would discard that case anyway).
		if ( $page->getNamespace() !== NS_MAIN
			|| $isSuggestedEdit
			|| !self::isLevelingUpEnabledForUser( $user, $this->config, $this->experimentUserManager )
			|| !$this->levelingUpManager->shouldInviteUserAfterNormalEdit( $user )
		) {
			return;
		}

		$apiResponse['modules'][] = 'ext.growthExperiments.LevelingUp.InviteToSuggestedEdits';
		$apiResponse['jsconfigvars']['wgPostEditConfirmationDisabled'] = true;
	}

	/**
	 * Load the InviteToSuggestedEdits module when VE reloads the page after save (which it
	 * indicates by the use of the 'venotify' parameter).
	 *
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		// VE sets a query parameter, but there is no elegant way to detect post-edit reloads
		// in the wikitext editor. Check the JS variable that it uses to configure the notice.
		$isPostEditReload = $out->getRequest()->getCheck( 'venotify' )
			|| $out->getRequest()->getCheck( 'mfnotify' )
			|| ( $out->getJsConfigVars()['wgPostEdit'] ?? false );

		if (
			// Check that the feature is enabled, we are indeed in the post-edit reload of
			// an article, and the user just passed the threshold for the invite.
			!$isPostEditReload
			|| ( !$out->getTitle() || !$out->getTitle()->inNamespace( NS_MAIN ) )
			|| !self::isLevelingUpEnabledForUser( $out->getUser(), $this->config, $this->experimentUserManager )
			|| !$this->levelingUpManager->shouldInviteUserAfterNormalEdit( $out->getUser() )
		) {
			return;
		}

		$out->addModules( 'ext.growthExperiments.LevelingUp.InviteToSuggestedEdits' );
		// Disable the default core post-edit notice.
		$out->addJsConfigVars( 'wgPostEditConfirmationDisabled', true );
		$out->addJsConfigVars( 'wgGELevelingUpInviteToSuggestedEditsImmediate', true );
		$out->addJsConfigVars( 'wgCXSectionTranslationRecentEditInvitationSuppressed', true );
	}

	/**
	 * Add GrowthExperiments events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['ge-newcomer'] = [
			'tooltip' => 'echo-pref-tooltip-ge-newcomer',
		];
		$notifications['keep-going'] = [
			'category' => 'ge-newcomer',
			'group' => 'positive',
			'section' => 'message',
			'canNotifyAgent' => true,
			'presentation-model' => EchoKeepGoingPresentationModel::class,
			AttributeManager::ATTR_LOCATORS => [
				[ UserLocator::class . '::locateEventAgent' ]
			]
		];

		$icons['growthexperiments-keep-going'] = [
			'path' => 'GrowthExperiments/images/notifications-keep-going.svg'
		];

		$notifications['get-started'] = [
			'category' => 'ge-newcomer',
			'group' => 'positive',
			'section' => 'message',
			'canNotifyAgent' => true,
			'presentation-model' => EchoGetStartedPresentationModel::class,
			AttributeManager::ATTR_LOCATORS => [
				[ UserLocator::class . '::locateEventAgent' ]
			]
		];

		$icons['growthexperiments-get-started'] = [
			'path' => 'GrowthExperiments/images/notifications-get-started.svg'
		];
	}

	/** @inheritDoc */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions['echo-subscriptions-email-ge-newcomer'] = true;
		$defaultOptions['echo-subscriptions-web-ge-newcomer'] = true;
	}

	/**
	 * Whether leveling up features are available. This does not include any checks based on
	 * the user's contribution history, just site configuration and A/B test cohorts.
	 * @param UserIdentity $user
	 * @param Config $config Site configuration.
	 * @param ExperimentUserManager $experimentUserManager
	 * @return bool
	 */
	public static function isLevelingUpEnabledForUser(
		UserIdentity $user,
		Config $config,
		ExperimentUserManager $experimentUserManager
	): bool {
		// Leveling up should only be shown if
		// 1) suggested edits are available on this wiki, as we'll direct the user there
		// 2) the user's homepage is enabled, which maybe SuggestedEdits::isEnabled should
		//    check, but it doesn't (this also excludes autocreated potentially-experienced
		//    users who probably shouldn't get invites)
		// 3) (for now) the wiki is a pilot wiki and the user is in the experiment group
		return LevelingUpManager::isEnabledForAnyone( $config )
			&& SuggestedEdits::isEnabled( $config )
			&& HomepageHooks::isHomepageEnabled( $user )
			&& $experimentUserManager->isUserInVariant( $user, VariantHooks::VARIANT_CONTROL );
	}

}
