<?php

namespace GrowthExperiments;

use MediaWiki\User\Options\Hook\ConditionalDefaultOptionsAddConditionHook;
use MediaWiki\User\UserIdentity;

class ExperimentsHooks implements ConditionalDefaultOptionsAddConditionHook {

	private ExperimentUserDefaultsManager $experimentsManager;

	public function __construct( ExperimentUserDefaultsManager $experimentsManager ) {
		$this->experimentsManager = $experimentsManager;
	}

	public function onConditionalDefaultOptionsAddCondition( array &$extraConditions ): void {
		$experimentsManager = $this->experimentsManager;
		$extraConditions[ ExperimentUserDefaultsManager::CUCOND_BUCKET_BY_USER_ID ] = static function (
			UserIdentity $user, array $conditionArguments
		) use ( $experimentsManager ) {
			$experimentName = array_shift( $conditionArguments );
			return $experimentsManager->shouldAssignBucket( $user, $experimentName, $conditionArguments );
		};
	}
}
