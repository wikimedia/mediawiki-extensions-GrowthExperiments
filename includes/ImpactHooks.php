<?php

namespace GrowthExperiments;

use GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater;
use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Hook\ManualLogEntryBeforePublishHook;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;

class ImpactHooks implements
	ManualLogEntryBeforePublishHook
{

	private Config $config;
	private GrowthExperimentsUserImpactUpdater $userImpactUpdater;
	private UserFactory $userFactory;

	/**
	 * @param Config $config
	 * @param GrowthExperimentsUserImpactUpdater $userImpactUpdater
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		Config $config,
		GrowthExperimentsUserImpactUpdater $userImpactUpdater,
		UserFactory $userFactory
	) {
		$this->config = $config;
		$this->userImpactUpdater = $userImpactUpdater;
		$this->userFactory = $userFactory;
	}

	/** @inheritDoc */
	public function onManualLogEntryBeforePublish( $logEntry ): void {
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
		}
	}

}
