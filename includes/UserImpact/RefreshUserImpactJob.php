<?php

namespace GrowthExperiments\UserImpact;

use GenericParameterJob;
use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityLookup;

/**
 * Job for fetching, and computing expensive user impact data, then storing it.
 */
class RefreshUserImpactJob extends Job implements GenericParameterJob {

	private UserImpactStore $userImpactStore;
	private UserImpactLookup $userImpactLookup;
	private UserIdentityLookup $userIdentityLookup;

	/** @inheritDoc */
	public function __construct( $params = null ) {
		parent::__construct( 'refreshUserImpactJob', $params );
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->userImpactStore = $growthServices->getUserImpactStore();
		$this->userImpactLookup = $growthServices->getUserImpactLookup();
		$this->userIdentityLookup = $services->getUserIdentityLookup();
	}

	/** @inheritDoc */
	public function run() {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['userId'] );
		$logger = LoggerFactory::getInstance( 'GrowthExperiments' );
		$loggerParams = [ 'userId' => $this->params['userId'] ];
		if ( !$userIdentity ) {
			$logger->error(
				'Unable to get user identity in RefreshUserImpactJob.',
				$loggerParams
			);
			return false;
		}
		$userImpact = $this->userImpactLookup->getExpensiveUserImpact( $userIdentity );
		if ( $userImpact ) {
			$this->userImpactStore->setUserImpact( $userImpact );
		} else {
			$logger->error(
				'Unable to generate user impact for user in RefreshUserImpactJob.',
				$loggerParams
			);
		}
		return true;
	}

}
