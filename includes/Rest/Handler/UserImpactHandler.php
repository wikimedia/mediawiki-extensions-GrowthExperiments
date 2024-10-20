<?php

namespace GrowthExperiments\Rest\Handler;

use Exception;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\RefreshUserImpactJob;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use JobQueueGroup;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Stats\IBufferingStatsdDataFactory;

/**
 * Handler for POST and GET requests /growthexperiments/v0/user-impact/{user} endpoint.
 * Returns data about the user's impact on the wiki. POST is preferred for facilitating
 * writes to the database cache. Requests made with GET will use the job queue for
 * persisting impact data to the cache, which will take longer.
 */
class UserImpactHandler extends SimpleHandler {

	private UserImpactStore $userImpactStore;
	private UserImpactLookup $userImpactLookup;
	private UserImpactFormatter $userImpactFormatter;
	private IBufferingStatsdDataFactory $statsdDataFactory;
	private JobQueueGroup $jobQueueGroup;
	private UserFactory $userFactory;

	/**
	 * @param UserImpactStore $userImpactStore
	 * @param UserImpactLookup $userImpactLookup
	 * @param UserImpactFormatter $userImpactFormatter
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		UserImpactStore $userImpactStore,
		UserImpactLookup $userImpactLookup,
		UserImpactFormatter $userImpactFormatter,
		IBufferingStatsdDataFactory $statsdDataFactory,
		JobQueueGroup $jobQueueGroup,
		UserFactory $userFactory
	) {
		$this->userImpactStore = $userImpactStore;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactFormatter = $userImpactFormatter;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->userFactory = $userFactory;
	}

	/**
	 * @param UserIdentity $user
	 * @return array
	 * @throws HttpException
	 * @throws Exception
	 */
	public function run( UserIdentity $user ) {
		$start = microtime( true );
		$userImpact = $this->getUserImpact( $user );
		$validParams = $this->getValidatedParams();
		$pageviewsUrlDisplayLanguageCode = 'en';
		if ( $validParams[ 'lang' ] ) {
			$pageviewsUrlDisplayLanguageCode = $validParams[ 'lang' ];
		}
		$formattedJsonData = $this->userImpactFormatter->format( $userImpact, $pageviewsUrlDisplayLanguageCode );
		$this->statsdDataFactory->timing(
			'timing.growthExperiments.UserImpactHandler.run', microtime( true ) - $start
		);
		return $formattedJsonData;
	}

	/**
	 * @param UserIdentity $user
	 * @return ExpensiveUserImpact
	 * @throws HttpException
	 */
	private function getUserImpact( UserIdentity $user ): ExpensiveUserImpact {
		if ( $this->getRequest()->getQueryParams()['regenerate'] ?? false ) {
			$cachedUserImpact = null;
		} else {
			$cachedUserImpact = $this->userImpactStore->getExpensiveUserImpact( $user );
		}
		if ( $cachedUserImpact ) {
			$this->statsdDataFactory->increment( 'GrowthExperiments.UserImpactHandler.Cache.Hit' );
		}

		if ( $cachedUserImpact && $cachedUserImpact->isPageViewDataStale() ) {
			// Page view data is stale; we will attempt to recalculate it.
			$userImpact = null;
			$this->statsdDataFactory->increment( 'GrowthExperiments.UserImpactHandler.Cache.HitStalePageViewData' );
		} else {
			$userImpact = $cachedUserImpact;
		}

		if ( !$userImpact ) {
			$this->statsdDataFactory->increment( 'GrowthExperiments.UserImpactHandler.Cache.Miss' );
			// Rate limit check.
			$performingUser = $this->userFactory->newFromUserIdentity( $this->getAuthority()->getUser() );
			if ( $performingUser->pingLimiter( 'growthexperimentsuserimpacthandler' ) ) {
				if ( $cachedUserImpact ) {
					$this->statsdDataFactory->increment(
						'GrowthExperiments.UserImpactHandler.PingLimiterTripped.StaleImpactData'
					);
					// Performing user is over the rate limit for requesting data for other users, but we have stale
					// data so just return that, rather than nothing.
					return $cachedUserImpact;
				} else {
					$this->statsdDataFactory->increment(
						'GrowthExperiments.UserImpactHandler.PingLimiterTripped.NoData'
					);
					throw new HttpException( 'Too many requests to refresh user impact data', 429 );
				}
			}
			$userImpact = $this->userImpactLookup->getExpensiveUserImpact( $user );
			if ( !$userImpact ) {
				throw new HttpException( 'Impact data not found for user', 404 );
			}
			// We want to write the updated data back to the cache table; doing that in a deferred update
			// is preferable as we don't depend on job queue functioning quickly, but that isn't allowed
			// on GET. So if a client is using a GET request, use the job queue, but otherwise (e.g. on
			// Special:Homepage) we'll use a POST request and save using a deferred update.
			if ( $this->getRequest()->getMethod() === 'GET' ) {
				$this->jobQueueGroup->lazyPush(
					new RefreshUserImpactJob( [
						'impactDataBatch' => [ $user->getId() => json_encode( $userImpact ) ],
					] )
				);
			} else {
				DeferredUpdates::addCallableUpdate( function () use ( $userImpact ) {
					$this->userImpactStore->setUserImpact( $userImpact );
				} );
			}
		}

		return $userImpact;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'user' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'user',
				ParamValidator::PARAM_REQUIRED => true,
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'id' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'lang' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'regenerate' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return true;
	}

}
