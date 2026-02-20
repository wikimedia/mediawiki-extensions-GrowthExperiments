<?php

namespace GrowthExperiments\LevelingUp;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\NewcomerTasks\AddALinkMilestonePresentationModel;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
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
	private LevelingUpManager $levelingUpManager;
	private FeatureManager $featureManager;

	/**
	 * @param Config $config
	 * @param ConfigurationLoader $configurationLoader
	 * @param LevelingUpManager $levelingUpManager
	 */
	public function __construct(
		Config $config,
		ConfigurationLoader $configurationLoader,
		LevelingUpManager $levelingUpManager,
		FeatureManager $featureManager
	) {
		$this->config = $config;
		$this->configurationLoader = $configurationLoader;
		$this->levelingUpManager = $levelingUpManager;
		$this->featureManager = $featureManager;
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
		if ( !$this->featureManager->isNewcomerTasksAvailable() ) {
			return;
		}
		$taskTypes = $this->configurationLoader->getTaskTypes();
		$pluginFields = array_map(
			static fn ( $taskTypeId ) => VisualEditorHooks::PLUGIN_PREFIX . $taskTypeId,
			array_keys( $taskTypes )
		);
		$isSuggestedEdit = count( array_intersect( $pluginFields, array_keys( $pluginData ) ) ) > 0;

		// Check that the feature is enabled, we are editing an article and the user
		// just passed the threshold for the invite.
		// Also check if the current edit is a suggested edit, as a micro-optimisation
		// (shouldInviteUserAfterNormalEdit() would discard that case anyway).
		if ( $page->getNamespace() !== NS_MAIN
			|| $isSuggestedEdit
			|| !LevelingUpManager::isEnabledForUser( $user, $this->config )
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
		$user = $out->getUser();
		if (
			$user->isRegistered()
			&& LevelingUpManager::isEnabledForUser( $user, $this->config )
			&& $this->config->get( 'GELevelingUpNotificationsTrackingEnabled' )
		) {
			$out->addModules( 'ext.growthExperiments.NotificationsTracking' );
		}
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
			|| !LevelingUpManager::isEnabledForUser( $user, $this->config )
			|| !$this->levelingUpManager->shouldInviteUserAfterNormalEdit( $user )
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
		$growthNotificationDefaults = [
			'category' => 'ge-newcomer',
			'group' => 'positive',
			'section' => 'message',
			'canNotifyAgent' => true,
			AttributeManager::ATTR_LOCATORS => [ UserLocator::locateEventAgent( ... ) ],
		];
		// Keep keep-going to not modify copy of already sent notifications
		$notifications['keep-going'] = array_merge( $growthNotificationDefaults, [
			'presentation-model' => EchoKeepGoingBasePresentationModel::class,
		] );
		$notifications['keep-going-exploring'] = array_merge( $growthNotificationDefaults, [
			'presentation-model' => EchoKeepGoingPresentationModel::class,
		] );
		// Keep get-started to not modify copy of already sent notifications
		$notifications['get-started'] = array_merge( $growthNotificationDefaults, [
			'presentation-model' => EchoGetStartedBasePresentationModel::class,
		] );
		$notifications['get-started-no-edits'] = array_merge( $notifications['get-started'], [
			'presentation-model' => EchoGetStartedPresentationModel::class,
		] );
		$notifications['re-engage'] = array_merge( $growthNotificationDefaults, [
			'presentation-model' => EchoReEngagePresentationModel::class,
		] );
		$notifications['newcomer-milestone-reached'] = array_merge( $growthNotificationDefaults, [
			'presentation-model'  => AddALinkMilestonePresentationModel::class,
		] );

		$icons['growthexperiments-keep-going'] = [
			'path' => 'GrowthExperiments/images/notifications-keep-going.svg',
		];

		$icons['growthexperiments-get-started'] = [
			'path' => 'GrowthExperiments/images/notifications-get-started.svg',
		];

		$icons['growthexperiments-addalink-milestone'] = [
			'path' => 'GrowthExperiments/images/addalink-milestone.svg',
		];
	}

	/** @inheritDoc */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions['echo-subscriptions-email-ge-newcomer'] = true;
		$defaultOptions['echo-subscriptions-web-ge-newcomer'] = true;
	}

}
