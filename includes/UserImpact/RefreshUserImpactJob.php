<?php

namespace GrowthExperiments\UserImpact;

use GenericParameterJob;
use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityLookup;
use MWTimestamp;
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
		$this->userIdentityLookup = $services->getUserIdentityLookup();
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
			$userImpact = null;
			/** @var UserImpact $preloadedUserImpact */
			$preloadedUserImpact = $preloadedUserImpacts[$userId] ?? null;
			if ( $impactJson ) {
				try {
					$userImpact = UserImpact::newFromJsonArray( json_decode( $impactJson, true ) );
					// Do not update the cache is it is already more recent.
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

			if ( !$userImpact ) {
				$userImpact = $this->computeUserImpact( $userId );
			}

			if ( $userImpact ) {
				$this->userImpactStore->setUserImpact( $userImpact );
			}
		}
		return true;
	}

	/**
	 * @param UserImpact $impact
	 * @return bool
	 */
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
