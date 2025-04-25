<?php

declare( strict_types = 1 );

namespace GrowthExperiments\UserImpact;

use DateTime;
use GrowthExperiments\HomepageHooks;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class GrowthExperimentsUserImpactUpdater {
	private UserIdentityUtils $userIdentityUtils;
	private UserOptionsLookup $userOptionsLookup;
	private UserEditTracker $userEditTracker;
	private UserFactory $userFactory;
	private JobQueueGroup $jobQueueGroup;
	private UserImpactLookup $userImpactLookup;
	private UserImpactStore $userImpactStore;
	private UserImpactFormatter $userImpactFormatter;

	public function __construct(
		UserIdentityUtils $userIdentityUtils,
		UserOptionsLookup $userOptionsLookup,
		UserEditTracker $userEditTracker,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		UserImpactLookup $userImpactLookup,
		UserImpactStore $userImpactStore,
		UserImpactFormatter $userImpactFormatter
	) {
		$this->userIdentityUtils = $userIdentityUtils;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userEditTracker = $userEditTracker;
		$this->userFactory = $userFactory;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactStore = $userImpactStore;
		$this->userImpactFormatter = $userImpactFormatter;
	}

	/**
	 * Account is considered to be in the Impact module data cohort if:
	 * - is registered, AND
	 * - has homepage preference enabled, AND
	 * - has edited, AND
	 * - created in the last year OR edited within the last 7 days
	 */
	public function userIsInCohort( UserIdentity $userIdentity ): bool {
		if ( !$this->userIdentityUtils->isNamed( $userIdentity ) ) {
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
	 * Refresh the user impact data after a thanks is received or the user makes an edit.
	 *
	 * @todo Make this check userIsInCohort()?
	 *
	 * @param UserIdentity $userIdentity
	 * @return void
	 */
	public function refreshUserImpactData( UserIdentity $userIdentity ): void {
		// In a job queue context, we have more flexibility for ensuring that the user's page view data is
		// reasonably complete, because we're not blocking a web request. In the web context, we will make
		// a maximum of 5 requests to the AQS service to fetch page views for the user's "top articles".
		// If the user has a cached impact, get the top viewed articles from that and place define them
		// as the priority articles for fetching page view data. If somehow those articles are in fact not
		// the top viewed articles, the refreshUserImpactJob will fix that when it runs.
		$cachedImpact = $this->userImpactStore->getExpensiveUserImpact( $userIdentity );
		$priorityArticles = [];
		if ( $cachedImpact ) {
			$sortedAndFilteredData = $this->userImpactFormatter->sortAndFilter( $cachedImpact->jsonSerialize() );
			if ( count( $sortedAndFilteredData['topViewedArticles'] ) ) {
				// We need an array of titles, where the keys contain the titles in DBKey format.
				$priorityArticles = $sortedAndFilteredData['topViewedArticles'];
			}
		}
		$impact = $this->userImpactLookup->getExpensiveUserImpact(
			$userIdentity,
			IDBAccessObject::READ_LATEST,
			$priorityArticles
		);
		if ( $impact ) {
			$this->userImpactStore->setUserImpact( $impact );
			// Also enqueue a job, so that we can get accurate page view data for users who aren't in
			// the filters defined for the refreshUserImpact.php cron job.
			$this->jobQueueGroup->push( new RefreshUserImpactJob( [
				'impactDataBatch' => [ $userIdentity->getId() => json_encode( $impact ) ],
				// We want to regenerate the page view data, so set staleBefore that's
				// guaranteed to result in cache invalidation
				'staleBefore' => MWTimestamp::time() + ExpirationAwareness::TTL_SECOND
			] ) );
		}
	}
}
