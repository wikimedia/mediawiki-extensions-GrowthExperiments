<?php

namespace GrowthExperiments\LevelingUp;

use Config;
use EchoAttributeManager;
use EchoUserLocator;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\VariantHooks;
use GrowthExperiments\VisualEditorHooks;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Hook\BeforePageDisplayHook;
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
		if ( !$this->config->get( 'GELevelingUpFeaturesEnabled' ) ) {
			return;
		}

		// Ignore suggested edits.
		$taskTypes = $this->configurationLoader->getTaskTypes();
		$pluginFields = array_map(
			fn( $taskTypeId ) => VisualEditorHooks::PLUGIN_PREFIX . $taskTypeId,
			array_keys( $taskTypes )
		);
		if ( array_intersect( $pluginFields, array_keys( $pluginData ) ) ) {
			return;
		}

		if (
			!$this->experimentUserManager->isUserInVariant( $user, VariantHooks::VARIANT_CONTROL )
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
			// Check that the feature is enabled, we are indeed in a post-edit reload, and
			// the user just passed the threshold for the invite.
			!$this->config->get( 'GELevelingUpFeaturesEnabled' )
			|| !$isPostEditReload
			|| !$this->experimentUserManager->isUserInVariant( $out->getUser(), VariantHooks::VARIANT_CONTROL )
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
			EchoAttributeManager::ATTR_LOCATORS => [
				[ EchoUserLocator::class . '::locateEventAgent' ]
			]
		];

		$icons['growthexperiments-keep-going'] = [
			'path' => 'GrowthExperiments/images/notifications-keep-going.svg'
		];
	}

	/** @inheritDoc */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions['echo-subscriptions-email-ge-newcomer'] = true;
		$defaultOptions['echo-subscriptions-web-ge-newcomer'] = true;
	}
}
