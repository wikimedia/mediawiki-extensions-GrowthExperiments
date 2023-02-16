<?php

namespace GrowthExperiments\LevelingUp;

use Config;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\VisualEditorHooks;
use MediaWiki\Extension\VisualEditor\VisualEditorApiVisualEditorEditPostSaveHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;

/**
 * Hooks for the "leveling up" feature.
 * @see https://www.mediawiki.org/wiki/Growth/Positive_reinforcement#Leveling_up
 */
class LevelingUpHooks implements
	VisualEditorApiVisualEditorEditPostSaveHook
{

	private Config $config;
	private ConfigurationLoader $configurationLoader;
	private LevelingUpManager $levelingUpManager;

	/**
	 * @param Config $config
	 * @param ConfigurationLoader $configurationLoader
	 * @param LevelingUpManager $levelingUpManager
	 */
	public function __construct(
		Config $config,
		ConfigurationLoader $configurationLoader,
		LevelingUpManager $levelingUpManager
	) {
		$this->config = $config;
		$this->configurationLoader = $configurationLoader;
		$this->levelingUpManager = $levelingUpManager;
	}

	/**
	 * Indicate to the client-side logic that the user should be invited.
	 * We could use RevisionFromEditComplete for visual edits and look for cookies on the API
	 * response, but we have a nicer option so why not use it.
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

		if ( !$this->levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) ) {
			return;
		}

		$apiResponse['growthexperiments']['levelingup']['invited'] = true;
	}

}
