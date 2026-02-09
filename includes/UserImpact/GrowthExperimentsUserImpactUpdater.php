<?php

declare( strict_types = 1 );

namespace GrowthExperiments\UserImpact;

use DateTime;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class GrowthExperimentsUserImpactUpdater {
	private UserEditTracker $userEditTracker;
	private UserRegistrationLookup $userRegistrationLookup;
	private JobQueueGroup $jobQueueGroup;
	private UserImpactLookup $userImpactLookup;
	private UserImpactStore $userImpactStore;
	private UserImpactFormatter $userImpactFormatter;

	public function __construct(
		UserEditTracker $userEditTracker,
		UserRegistrationLookup $userRegistrationLookup,
		JobQueueGroup $jobQueueGroup,
		UserImpactLookup $userImpactLookup,
		UserImpactStore $userImpactStore,
		UserImpactFormatter $userImpactFormatter
	) {
		$this->userEditTracker = $userEditTracker;
		$this->userRegistrationLookup = $userRegistrationLookup;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactStore = $userImpactStore;
		$this->userImpactFormatter = $userImpactFormatter;
	}

	/**
	 * Account is considered to be in the Impact module data cohort if:
	 * - is registered (named or temp), AND
	 * - has edited, AND
	 * - created in the last year OR edited within the last 7 days
	 */
	public function userIsInCohort( UserIdentity $userIdentity ): bool {
		// UserImpact isn't for legacy IP actors.
		if ( !$userIdentity->isRegistered() ) {
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
		$registrationTimestamp = wfTimestamp( TS_UNIX,
			$this->userRegistrationLookup->getRegistration( $userIdentity ) );

		$lastEditTimestamp = MWTimestamp::getInstance( $lastEditTimestamp );
		$lastEditAge = $lastEditTimestamp->diff( new ConvertibleTimestamp( new DateTime( 'now - 1week' ) ) );
		if ( !$lastEditAge ) {
			return false;
		}

		return $registrationTimestamp >= $registrationCutoff->getTimestamp() || $lastEditAge->days <= 7;
	}

	/**
	 * Refresh the user impact data after a thanks is received or the user makes an edit.
	 *
	 * @todo Make this check userIsInCohort()?
	 *
	 * @param UserIdentity $userIdentity
	 * @return void
	 */
	public function refreshUserImpactData( UserIdentity $userIdentity ): void {
		// Previously, there was code was pre-computing UserImpact and saving it. The purpose was
		// to update the part of UserImpact that needs SQL queries only immediately, and to
		// refresh the page views data (which needs a bunch of AQS queries, which take time) in a
		// job. This caused a large volume of primary DB reads (T416171), so it was removed,
		// accepting the delay.

		$this->jobQueueGroup->push( new JobSpecification( RefreshUserImpactJob::JOB_NAME, [
			'impactDataBatch' => [ $userIdentity->getId() => null ],
			// We want to regenerate the page view data, so set staleBefore that's
			// guaranteed to result in cache invalidation
			'staleBefore' => MWTimestamp::time() + ExpirationAwareness::TTL_SECOND,
		] ) );
	}
}
