<?php

namespace GrowthExperiments\NewcomerTasks\SurfacingStructuredTasks;

use GrowthExperiments\EventLogging\GrowthExperimentsInteractionLogger;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\User\Options\UserOptionsLookup;

class BeforePageDisplayHookHandler implements BeforePageDisplayHook {

	public const MAX_USER_EDITS = 100;
	private Config $config;
	private ConfigurationLoader $configurationLoader;
	private UserOptionsLookup $userOptionsLookup;
	private GrowthExperimentsInteractionLogger $growthInteractionLogger;
	private LinkRecommendationStore $linkRecommendationStore;

	public function __construct(
		Config $config,
		ConfigurationLoader $configurationLoader,
		UserOptionsLookup $userOptionsLookup,
		LinkRecommendationStore $linkRecommendationStore,
		GrowthExperimentsInteractionLogger $growthInteractionLogger
	) {
		$this->config = $config;
		$this->configurationLoader = $configurationLoader;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->growthInteractionLogger = $growthInteractionLogger;
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

		$page = $out->getTitle();
		if ( !$page || $page->getNamespace() !== NS_MAIN ) {
			return;
		}

		if ( !$user->probablyCan( 'edit', $page ) ) {
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

		if ( $user->getEditCount() >= self::MAX_USER_EDITS ) {
			return;
		}

		if ( $this->linkRecommendationStore->getByPageId( $page->getArticleID() ) === null ) {
			return;
		}

		$taskTypes = $this->configurationLoader->getTaskTypes();
		if ( !isset( $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ) ) {
			return;
		}

		$variant = $this->userOptionsLookup->getOption( $user, VariantHooks::USER_PREFERENCE );
		$this->growthInteractionLogger->log( $user, 'experiment_enrollment', [
			'action_source' => 'BeforePageDisplayHook',
			'variant' => $variant
		] );
		if ( $variant !== VariantHooks::VARIANT_SURFACING_STRUCTURED_TASK ) {
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
