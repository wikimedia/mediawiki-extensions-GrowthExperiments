<?php

namespace GrowthExperiments;

use GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Hook\ManualLogEntryBeforePublishHook;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;

class ImpactHooks implements
	ManualLogEntryBeforePublishHook
{
	private GrowthExperimentsUserImpactUpdater $userImpactUpdater;
	private UserFactory $userFactory;

	public function __construct(
		GrowthExperimentsUserImpactUpdater $userImpactUpdater,
		UserFactory $userFactory
	) {
		$this->userImpactUpdater = $userImpactUpdater;
		$this->userFactory = $userFactory;
	}

	/** @inheritDoc */
	public function onManualLogEntryBeforePublish( $logEntry ): void {
		// If the log entry is related to the thanks feature, check if the recipient or performer
		// is in the UserImpactUpdater cohort, and refresh their cached user impact data if so.
		if ( $logEntry->getType() === 'thanks' && $logEntry->getSubtype() === 'thank' ) {
			$recipientUserPage = $logEntry->getTarget();
			$user = $this->userFactory->newFromName( $recipientUserPage->getDBkey() );
			if (
				$user instanceof UserIdentity &&
				$this->userImpactUpdater->userIsInCohort( $user )
			) {
				DeferredUpdates::addCallableUpdate( function () use ( $user ) {
					$this->userImpactUpdater->refreshUserImpactData( $user );
				} );
			}
			$performer = $logEntry->getPerformerIdentity();
			if ( $this->userImpactUpdater->userIsInCohort( $performer ) ) {
				DeferredUpdates::addCallableUpdate( function () use ( $performer ) {
					$this->userImpactUpdater->refreshUserImpactData( $performer );
				} );
			}
		}
	}

}
