<?php

namespace GrowthExperiments\UserImpact;

use GenericParameterJob;
use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * Job for fetching, and computing expensive user impact data, then storing it.
 */
class RefreshUserImpactJob extends Job implements GenericParameterJob {

	private UserImpactStore $userImpactStore;
	private UserImpactLookup $userImpactLookup;
	private UserIdentityLookup $userIdentityLookup;

	/**
	 * Map of user ID => impact data as JSON string, or null to generate in the job
	 * @var (string|null)[]
	 */
	private array $impactDataBatch;

	/**
	 * @inheritDoc
	 * Parameters:
	 * - impactDataBatch: user impact data to write/compute, see self::$impactDataBatch
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

		$this->impactDataBatch = $params['impactDataBatch']
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			?? [ $params['userId'] => $params['impactData'] ?? null ];
	}

	/** @inheritDoc */
	public function run() {
		$logger = LoggerFactory::getInstance( 'GrowthExperiments' );
		foreach ( $this->impactDataBatch as $userId => $impactJson ) {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
			$loggerParams = [ 'userId' => $userId ];
			if ( !$userIdentity ) {
				$logger->error(
					'Unable to get user identity in RefreshUserImpactJob.',
					$loggerParams
				);
				return false;
			}
			$userImpact = null;
			if ( $impactJson ) {
				try {
					$userImpact = UserImpact::newFromJsonArray( json_decode( $impactJson, true ) );
				} catch ( ParameterAssertionException $parameterAssertionException ) {
					// Invalid cache format used, recalculate from scratch.
				}
			}
			if ( !$userImpact ) {
				$userImpact = $this->userImpactLookup->getExpensiveUserImpact( $userIdentity );
			}
			if ( $userImpact ) {
				$this->userImpactStore->setUserImpact( $userImpact );
			} else {
				$logger->error(
					'Unable to generate user impact for user in RefreshUserImpactJob.',
					$loggerParams
				);
			}
		}
		return true;
	}

}
