<?php

namespace GrowthExperiments\NewcomerTasks\SurfacingStructuredTasks;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\User\Options\UserOptionsLookup;

class BeforePageDisplayHookHandler implements BeforePageDisplayHook {

	private Config $config;
	private ConfigurationLoader $configurationLoader;
	private UserOptionsLookup $userOptionsLookup;

	public function __construct(
		Config $config,
		ConfigurationLoader $configurationLoader,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->config = $config;
		$this->configurationLoader = $configurationLoader;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->get( 'GESurfacingStructuredTasksEnabled' ) ) {
			return;
		}
		$user = $out->getUser();
		if ( !$user->isNamed() ) {
			return;
		}

		$variant = $this->userOptionsLookup->getOption( $user, VariantHooks::USER_PREFERENCE );
		if ( $variant !== VariantHooks::VARIANT_SURFACING_STRUCTURED_TASK ) {
			return;
		}

		$page = $out->getTitle();
		if ( !$page || $page->getNamespace() !== NS_MAIN ) {
			return;
		}

		$action = $out->getRequest()->getVal( 'action', 'view' );
		if ( $action !== 'view' ) {
			return;
		}

		$veaction = $out->getRequest()->getVal( 'veaction', null );
		if ( $veaction !== null ) {
			return;
		}

		if ( $user->getEditCount() !== 0 ) {
			return;
		}

		$taskTypes = $this->configurationLoader->getTaskTypes();
		if ( !isset( $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ) ) {
			return;
		}

		$linkRecommendationTaskType = $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID];
		'@phan-var \GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType $linkRecommendationTaskType';

		$maxLinks = $linkRecommendationTaskType->getMaximumLinksToShowPerTask();
		$minScore = $linkRecommendationTaskType->getMinimumLinkScore();
		$out->addJsConfigVars( 'wgGrowthExperimentsLinkRecommendationTask', [
			'maxLinks' => $maxLinks,
			'minScore' => $minScore,
		] );
		$out->enableOOUI();
		$out->addModules( 'ext.growthExperiments.StructuredTask.Surfacing' );
	}
}
