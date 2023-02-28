<?php

namespace GrowthExperiments;

use Config;
use DateTime;
use DeferredUpdates;
use GrowthExperiments\UserImpact\RefreshUserImpactJob;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use IDBAccessObject;
use JobQueueGroup;
use MediaWiki\Hook\ManualLogEntryBeforePublishHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MWTimestamp;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
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
	private UserEditTracker $userEditTracker;
	private UserImpactFormatter $userImpactFormatter;
	private JobQueueGroup $jobQueueGroup;

	/**
	 * @param Config $config
	 * @param UserImpactLookup $userImpactLookup
	 * @param UserImpactStore $userImpactStore
	 * @param UserImpactFormatter $userImpactFormatter
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserEditTracker $userEditTracker
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		Config $config,
		UserImpactLookup $userImpactLookup,
		UserImpactStore $userImpactStore,
		UserImpactFormatter $userImpactFormatter,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserEditTracker $userEditTracker,
		JobQueueGroup $jobQueueGroup
	) {
		$this->config = $config;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactStore = $userImpactStore;
		$this->userImpactFormatter = $userImpactFormatter;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->userEditTracker = $userEditTracker;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/** @inheritDoc */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( !$this->config->get( 'GEUseNewImpactModule' ) ) {
			return;
		}
		// Refresh the user's impact after they've made an edit.
		if ( $this->userIsInImpactDataCohort( $user ) &&
			$user->equals( $revisionRecord->getUser() )
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
	 * Refresh the user impact data after a thanks is received or the user makes an edit.
	 *
	 * @param UserIdentity $userIdentity
	 * @return void
	 */
	private function refreshUserImpactDataInDeferredUpdate( UserIdentity $userIdentity ): void {
		DeferredUpdates::addCallableUpdate( function () use ( $userIdentity ) {
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
		} );
	}
}
