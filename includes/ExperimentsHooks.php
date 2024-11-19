<?php

namespace GrowthExperiments;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\Options\Hook\ConditionalDefaultOptionsAddConditionHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;

class ExperimentsHooks implements ConditionalDefaultOptionsAddConditionHook {

	private ExperimentUserDefaultsManager $experimentsManager;
	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		ExperimentUserDefaultsManager $experimentsManager, UserIdentityUtils $userIdentityUtils
	) {
		$this->experimentsManager = $experimentsManager;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	public function onConditionalDefaultOptionsAddCondition( array &$extraConditions ): void {
		$experimentsManager = $this->experimentsManager;
		$userIdentityUtils = $this->userIdentityUtils;
		$extraConditions[ ExperimentUserDefaultsManager::CUCOND_BUCKET_BY_USER_ID ] = static function (
			UserIdentity $user, array $conditionArguments
		) use ( $experimentsManager, $userIdentityUtils ) {
			// Avoid assigning variant for anon and temporary users, T380294
			if ( !$user->isRegistered() || $userIdentityUtils->isTemp( $user ) ) {
				LoggerFactory::getInstance( 'GrowthExperiments' )
					->debug( 'Suspicious evaluation of unamed user in ExperimentsHooks closure', [
						'exception' => new \RuntimeException,
						'userName' => $user->getName(),
						'trace' => \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )
					] );

				return false;
			}
			$experimentName = array_shift( $conditionArguments );
			return $experimentsManager->shouldAssignBucket( $user, $experimentName, $conditionArguments );
		};
	}
}
