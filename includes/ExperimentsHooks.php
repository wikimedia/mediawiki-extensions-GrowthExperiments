<?php

namespace GrowthExperiments;

use MediaWiki\User\Options\Hook\ConditionalDefaultOptionsAddConditionHook;
use MediaWiki\User\UserIdentity;

class ExperimentsHooks implements ConditionalDefaultOptionsAddConditionHook {

	private ExperimentUserDefaultsManager $experimentsManager;

	public function __construct( ExperimentUserDefaultsManager $experimentsManager ) {
		$this->experimentsManager = $experimentsManager;
	}

	/**
	 * Register additional conditions to be evaluated by ConditionalDefaultsLookup. CUCOND_BUCKET_BY_USER_ID
	 * uses central user IDs as opposed to CUCOND_BUCKET_BY_LOCAL_USER_ID which uses local users.
	 */
	public function onConditionalDefaultOptionsAddCondition( array &$extraConditions ): void {
		$experimentsManager = $this->experimentsManager;
		$extraConditions[ ExperimentUserDefaultsManager::CUCOND_BUCKET_BY_USER_ID ] = static function (
			UserIdentity $user, array $conditionArguments
		) use ( $experimentsManager ) {
			$experimentName = array_shift( $conditionArguments );
			return $experimentsManager->shouldAssignGlobalBucket( $user, $experimentName, $conditionArguments );
		};
		$extraConditions[ ExperimentUserDefaultsManager::CUCOND_BUCKET_BY_LOCAL_USER_ID ] = static function (
			UserIdentity $user, array $conditionArguments
		) use ( $experimentsManager ) {
			$experimentName = array_shift( $conditionArguments );
			return $experimentsManager->shouldAssignLocalBucket( $user, $experimentName, $conditionArguments );
		};
	}
}
