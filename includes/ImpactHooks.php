<?php

namespace GrowthExperiments;

use Config;
use DateTime;
use DeferredUpdates;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use IDBAccessObject;
use MediaWiki\Hook\ManualLogEntryBeforePublishHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MWTimestamp;
use Wikimedia\Rdbms\LoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class ImpactHooks implements
	PageSaveCompleteHook,
	ManualLogEntryBeforePublishHook
{

	private Config $config;
	private UserImpactLookup $userImpactLookup;
	private UserImpactStore $userImpactStore;
	private UserOptionsLookup $userOptionsLookup;
	private UserFactory $userFactory;
	private LoadBalancer $loadBalancer;
	private UserEditTracker $userEditTracker;

	/**
	 * @param Config $config
	 * @param UserImpactLookup $userImpactLookup
	 * @param UserImpactStore $userImpactStore
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param LoadBalancer $loadBalancer
	 * @param UserEditTracker $userEditTracker
	 */
	public function __construct(
		Config $config,
		UserImpactLookup $userImpactLookup,
		UserImpactStore $userImpactStore,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		LoadBalancer $loadBalancer,
		UserEditTracker $userEditTracker
	) {
		$this->config = $config;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactStore = $userImpactStore;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->loadBalancer = $loadBalancer;
		$this->userEditTracker = $userEditTracker;
	}

	/** @inheritDoc */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( !$this->config->get( 'GEUseNewImpactModule' ) ) {
			return;
		}
		// Refresh the user's impact after they've made an edit.
		if ( $this->userIsInImpactDataCohort( $user ) &&
			$user->equals( $revisionRecord->getUser() ) &&
			$wikiPage->getNamespace() === NS_MAIN
		) {
			$this->refreshUserImpactDataInDeferredUpdate( $user );

		}
	}

	/** @inheritDoc */
	public function onManualLogEntryBeforePublish( $logEntry ): void {
		if ( !$this->config->get( 'GEUseNewImpactModule' ) ) {
			return;
		}
		if ( $logEntry->getType() === 'thanks' && $logEntry->getSubtype() === 'thank' ) {
			$recipientUserPage = $logEntry->getTarget();
			$user = $this->userFactory->newFromName( $recipientUserPage->getDBkey() );
			if ( $user instanceof UserIdentity && $this->userIsInImpactDataCohort( $user ) ) {
				$this->refreshUserImpactDataInDeferredUpdate( $user );
			}
		}
	}

	/**
	 * Account is considered to be in the Impact module data cohort if:
	 * - is registered, AND
	 * - has homepage preference enabled, AND
	 * - has edited, AND
	 * - created in the last year OR edited within the last 7 days
	 * @param UserIdentity $userIdentity
	 * @return bool
	 */
	private function userIsInImpactDataCohort( UserIdentity $userIdentity ): bool {
		if ( !$userIdentity->isRegistered() ) {
			return false;
		}
		if ( !$this->userOptionsLookup->getBoolOption( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE ) ) {
			return false;
		}
		$lastEditTimestamp = $this->userEditTracker->getLatestEditTimestamp(
			$userIdentity,
			IDBAccessObject::READ_LATEST
		);
		if ( !$lastEditTimestamp ) {
			return false;
		}

		$registrationCutoff = new DateTime( 'now - 1year' );
		$user = $this->userFactory->newFromUserIdentity( $userIdentity );
		$registrationTimestamp = wfTimestamp( TS_UNIX, $user->getRegistration() );

		$lastEditTimestamp = MWTimestamp::getInstance( $lastEditTimestamp );
		$lastEditAge = $lastEditTimestamp->diff( new ConvertibleTimestamp( new DateTime( 'now - 1week' ) ) );
		if ( !$lastEditAge ) {
			return false;
		}

		return $registrationTimestamp >= $registrationCutoff->getTimestamp() || $lastEditAge->days <= 7;
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @return void
	 */
	private function refreshUserImpactDataInDeferredUpdate( UserIdentity $userIdentity ): void {
		DeferredUpdates::addCallableUpdate( function () use ( $userIdentity ) {
			$impact = $this->userImpactLookup->getExpensiveUserImpact( $userIdentity, IDBAccessObject::READ_LATEST );
			if ( $impact ) {
				$this->userImpactStore->setUserImpact( $impact );
			}
		} );
	}
}
