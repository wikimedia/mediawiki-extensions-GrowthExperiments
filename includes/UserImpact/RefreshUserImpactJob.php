<?php

namespace GrowthExperiments\UserImpact;

use GenericParameterJob;
use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\Utils\MWTimestamp;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;

/**
 * Job for computing and caching expensive user impact data. Can also be used to refresh the cache
 * with an already computed value.
 */
class RefreshUserImpactJob extends Job implements GenericParameterJob {

	private UserImpactStore $userImpactStore;
	private UserImpactLookup $userImpactLookup;
	private UserFactory $userFactory;
	private UserImpactFormatter $userImpactFormatter;
	private UserIdentityLookup $userIdentityLookup;
	private LoggerInterface $logger;

	/**
	 * Map of user ID => impact data as JSON string, or null to generate in the job
	 * @var (string|null)[]
	 */
	private array $impactDataBatch;

	/**
	 * Cached objects generated before this UNIX timestamp are considered stale and recomputed.
	 * Only used when no impact data is provided for the given user.
	 * @var int
	 */
	private int $staleBefore;

	/**
	 * @inheritDoc
	 * Parameters:
	 * - impactDataBatch: user impact data to write/compute, see self::$impactDataBatch
	 * - staleBefore: staleness limit, see self::$staleBefore; optional, defaults to 1 day ago
	 * - userId: user to refresh data for (deprecated, required if impactDataBatch not present)
	 * - impactData: impact data for userId (deprecated)
	 */
	public function __construct( $params = null ) {
		parent::__construct( 'refreshUserImpactJob', $params );

		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->userImpactStore = $growthServices->getUserImpactStore();
		$this->userImpactLookup = $growthServices->getUserImpactLookup();
		$this->userImpactFormatter = $growthServices->getUserImpactFormatter();
		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->userFactory = $services->getUserFactory();
		$this->logger = LoggerFactory::getInstance( 'GrowthExperiments' );

		$this->impactDataBatch = $params['impactDataBatch']
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			?? [ $params['userId'] => $params['impactData'] ?? null ];
		$this->staleBefore = $params['staleBefore'] ?? MWTimestamp::time() - ExpirationAwareness::TTL_DAY;
		// Prevent accidental use of TS_MW or some other non-TS_UNIX format but don't require int type
		// as e.g. wfTimestamp( TS_UNIX ) returns a string.
		Assert::parameter( is_numeric( $this->staleBefore ) && $this->staleBefore < 2147483647,
			'staleBefore', 'must be a UNIX timestamp' );
	}

	/** @inheritDoc */
	public function run() {
		$preloadedUserImpacts = [];
		if ( $this->userImpactStore instanceof DatabaseUserImpactStore ) {
			$preloadedUserImpacts = $this->userImpactStore->batchGetUserImpact(
				array_keys( $this->impactDataBatch )
			);
		}
		foreach ( $this->impactDataBatch as $userId => $impactJson ) {
			if ( $this->userFactory->newFromId( $userId )->isHidden() ) {
				// do not update impact data for hidden users (T337845)
				continue;
			}

			$userImpact = null;
			/** @var UserImpact $preloadedUserImpact */
			$preloadedUserImpact = $preloadedUserImpacts[$userId] ?? null;
			if ( $impactJson ) {
				try {
					$userImpact = UserImpact::newFromJsonArray( json_decode( $impactJson, true ) );
					// Do not update the cache if it is already more recent.
					if ( $preloadedUserImpact
						&& $preloadedUserImpact->getGeneratedAt() > $userImpact->getGeneratedAt()
					) {
						continue;
					}
				} catch ( ParameterAssertionException $parameterAssertionException ) {
					// Invalid cache format used, recalculate from scratch.
				}
			} elseif ( $preloadedUserImpact && $this->isFresh( $preloadedUserImpact ) ) {
				// We haven't been explicitly told to save new data, and the existing data
				// is still usable, nothing to do.
				continue;
			}

			if ( !$userImpact || !$this->isFresh( $userImpact ) ) {
				$userImpact = $this->computeUserImpact( $userId );
			}

			if ( $userImpact ) {
				// We don't want to cache all page view data captured by ::computeUserImpact; in a job queue
				// context, this can contain up to 1000 articles of PageViewData (configured via
				// GEUserImpactMaxArticlesToProcessForPageviews). Call
				// the formatter to get just the data we need, and replace the dailyArticleViews with just the
				// top entries.
				$jsonData = $userImpact->jsonSerialize();
				$sortedAndFiltered = $this->userImpactFormatter->sortAndFilter( $jsonData );
				$jsonData['dailyArticleViews'] =
					// Make sure dailyArticleViews includes both the top viewed articles and recently edited
					// articles without page views. Those will both be used by UserImpactFormatter again when
					// fetching the data to display.
					$sortedAndFiltered['topViewedArticles'] + $sortedAndFiltered['recentEditsWithoutPageviews'];
				$userImpact = UserImpact::newFromJsonArray( $jsonData );
				$this->userImpactStore->setUserImpact( $userImpact );
			}
		}
		return true;
	}

	private function isFresh( UserImpact $impact ): bool {
		return $impact->getGeneratedAt() >= $this->staleBefore;
	}

	/**
	 * @param int $userId
	 * @return ExpensiveUserImpact|null
	 */
	private function computeUserImpact( int $userId ): ?ExpensiveUserImpact {
		$loggerParams = [ 'userId' => $userId ];
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
		if ( !$userIdentity ) {
			$this->logger->error(
				'Unable to get user identity in RefreshUserImpactJob.',
				$loggerParams
			);
			return null;
		}

		$userImpact = $this->userImpactLookup->getExpensiveUserImpact( $userIdentity );
		if ( !$userImpact ) {
			$this->logger->error(
				'Unable to generate user impact for user in RefreshUserImpactJob.',
				$loggerParams
			);
			return null;
		}

		return $userImpact;
	}

}
