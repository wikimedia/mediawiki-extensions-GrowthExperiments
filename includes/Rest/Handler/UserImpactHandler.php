<?php

namespace GrowthExperiments\Rest\Handler;

use Exception;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\RefreshUserImpactJob;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\MediaWikiServices;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Stats\StatsFactory;

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
	private StatsFactory $statsFactory;
	private JobQueueGroup $jobQueueGroup;
	private UserFactory $userFactory;

	/**
	 * @param UserImpactStore $userImpactStore
	 * @param UserImpactLookup $userImpactLookup
	 * @param UserImpactFormatter $userImpactFormatter
	 * @param StatsFactory $statsFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		UserImpactStore $userImpactStore,
		UserImpactLookup $userImpactLookup,
		UserImpactFormatter $userImpactFormatter,
		StatsFactory $statsFactory,
		JobQueueGroup $jobQueueGroup,
		UserFactory $userFactory
	) {
		$this->userImpactStore = $userImpactStore;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactFormatter = $userImpactFormatter;
		$this->statsFactory = $statsFactory;
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

		$runTimeInSeconds = microtime( true ) - $start;
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getTiming( 'user_impact_handler_run_seconds' )
			->observeSeconds( $runTimeInSeconds );

		// Stay backward compatible with the legacy Graphite-based dashboard
		// feeding on this data.
		// TODO: remove after switching to Prometheus-based dashboards
		MediaWikiServices::getInstance()->getStatsdDataFactory()->timing(
			'timing.growthExperiments.UserImpactHandler.run', $runTimeInSeconds
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
			$this->statsFactory->withComponent( 'GrowthExperiments' )
				->getCounter( 'user_impact_handler_cache_total' )
				->setLabel( 'status', 'hit' )
				->copyToStatsdAt( 'GrowthExperiments.UserImpactHandler.Cache.Hit' )
				->increment();
		}

		if ( $cachedUserImpact && $cachedUserImpact->isPageViewDataStale() ) {
			// Page view data is stale; we will attempt to recalculate it.
			$userImpact = null;
			$this->statsFactory->withComponent( 'GrowthExperiments' )
				->getCounter( 'user_impact_handler_cache_total' )
				->setLabel( 'status', 'hit_stale' )
				->copyToStatsdAt( 'GrowthExperiments.UserImpactHandler.Cache.HitStalePageViewData' )
				->increment();
		} else {
			$userImpact = $cachedUserImpact;
		}

		if ( !$userImpact ) {
			$this->statsFactory->withComponent( 'GrowthExperiments' )
				->getCounter( 'user_impact_handler_cache_total' )
				->setLabel( 'status', 'miss' )
				->copyToStatsdAt( 'GrowthExperiments.UserImpactHandler.Cache.Miss' )
				->increment();
			// Rate limit check.
			$performingUser = $this->userFactory->newFromUserIdentity( $this->getAuthority()->getUser() );
			if ( $performingUser->pingLimiter( 'growthexperimentsuserimpacthandler' ) ) {
				if ( $cachedUserImpact ) {
					$this->statsFactory->withComponent( 'GrowthExperiments' )
						->getCounter( 'user_impact_handler_ping_limiter_total' )
						->setLabel( 'status', 'stale_data' )
						->copyToStatsdAt(
							'GrowthExperiments.UserImpactHandler.PingLimiterTripped.StaleImpactData' )
						->increment();
					// Performing user is over the rate limit for requesting data for other users, but we have stale
					// data so just return that, rather than nothing.
					return $cachedUserImpact;
				} else {
					$this->statsFactory->withComponent( 'GrowthExperiments' )
						->getCounter( 'user_impact_handler_ping_limiter_total' )
						->setLabel( 'status', 'no_data' )
						->copyToStatsdAt(
							'GrowthExperiments.UserImpactHandler.PingLimiterTripped.NoData' )
						->increment();
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
