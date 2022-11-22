<?php

namespace GrowthExperiments\Rest\Handler;

use Config;
use DateTime;
use Exception;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\RefreshUserImpactJob;
use GrowthExperiments\UserImpact\SortedFilteredUserImpact;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use IBufferingStatsdDataFactory;
use JobQueueGroup;
use Language;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MWTimestamp;
use stdClass;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handler for the GET /growthexperiments/v0/user-impact/{user} endpoint.
 * Returns data about the user's impact on the wiki.
 */
class UserImpactHandler extends SimpleHandler {

	private Config $config;
	private stdClass $AQSConfig;
	private TitleFactory $titleFactory;
	private Language $contentLanguage;
	private IBufferingStatsdDataFactory $statsdDataFactory;
	private UserImpactStore $userImpactStore;
	private UserImpactLookup $userImpactLookup;
	private JobQueueGroup $jobQueueGroup;
	private UserFactory $userFactory;

	/**
	 * @param Config $config
	 * @param stdClass $AQSConfig
	 * @param UserImpactStore $userImpactStore
	 * @param UserImpactLookup $userImpactLookup
	 * @param TitleFactory $titleFactory
	 * @param Language $contentLanguage
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		Config $config,
		stdClass $AQSConfig,
		UserImpactStore $userImpactStore,
		UserImpactLookup $userImpactLookup,
		TitleFactory $titleFactory,
		Language $contentLanguage,
		IBufferingStatsdDataFactory $statsdDataFactory,
		JobQueueGroup $jobQueueGroup,
		UserFactory $userFactory
	) {
		$this->config = $config;
		$this->AQSConfig = $AQSConfig;
		$this->userImpactStore = $userImpactStore;
		$this->titleFactory = $titleFactory;
		$this->contentLanguage = $contentLanguage;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->userImpactLookup = $userImpactLookup;
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
		$performingUser = $this->userFactory->newFromUserIdentity( $this->getAuthority()->getUser() );
		$userImpact = $this->userImpactStore->getExpensiveUserImpact( $user );
		$staleUserImpact = $userImpact;

		if ( $userImpact && $this->isPageViewDataStale( $userImpact ) ) {
			// Page view data is stale; we will attempt to recalculate it.
			$userImpact = null;
		}

		if ( !$userImpact ) {
			// Rate limit check.
			if ( $performingUser->pingLimiter( 'growthexperimentsuserimpacthandler' ) ) {
				if ( $staleUserImpact ) {
					// Performing user is over the rate limit for requesting data for other users, but we have stale
					// data so just return that, rather than nothing.
					$jsonData = $staleUserImpact->jsonSerialize();
					$this->fillDailyArticleViewsWithPageViewToolsUrl( $jsonData );
					return $jsonData;
				}
				throw new HttpException( 'Too many requests to refresh user impact data', 429 );
			}
			$userImpact = $this->userImpactLookup->getExpensiveUserImpact( $user );
			if ( !$userImpact ) {
				throw new HttpException( 'Impact data not found for user', 404 );
			}
			$this->jobQueueGroup->lazyPush(
				new RefreshUserImpactJob( [
					'impactDataBatch' => [ $user->getId() => json_encode( $userImpact ) ],
				] )
			);
		}
		$jsonData = $userImpact->jsonSerialize();
		$this->fillDailyArticleViewsWithPageViewToolsUrl( $jsonData );
		$sortedFilteredUserImpact = SortedFilteredUserImpact::newFromUnsortedJsonArray( $jsonData );
		$jsonData = $sortedFilteredUserImpact->jsonSerialize();
		$this->statsdDataFactory->timing(
			'timing.growthExperiments.UserImpactHandler.run', microtime( true ) - $start
		);
		return $jsonData;
	}

	/**
	 * @param array &$jsonData
	 * @return void
	 * @throws Exception
	 */
	private function fillDailyArticleViewsWithPageViewToolsUrl(
		array &$jsonData
	): void {
		foreach ( $jsonData['dailyArticleViews'] as $title => $articleData ) {
			$jsonData['dailyArticleViews'][$title]['pageviewsUrl'] =
				$this->getPageViewToolsUrl( $title, $articleData['firstEditDate'] );
		}
	}

	/**
	 * @param ExpensiveUserImpact $userImpact
	 * @return bool
	 * @throws Exception
	 */
	private function isPageViewDataStale( ExpensiveUserImpact $userImpact ): bool {
		$latestPageViewsDateTime = new DateTime( array_key_last( $userImpact->getDailyTotalViews() ) );
		$now = MWTimestamp::getInstance();
		$diff = $now->timestamp->diff( $latestPageViewsDateTime );
		// Page view data generation can lag by 24-48 hours.
		// Consider the data stale if it's older than 2 days.
		return $diff->days > 2;
	}

	/**
	 * @param string $title
	 * @param string $firstEditDate Date of the first edit to the article in Y-m-d format.
	 * @throws Exception
	 * @return string Full URL for the PageViews tool for the given title and start date
	 */
	private function getPageViewToolsUrl( string $title, string $firstEditDate ): string {
		$baseUrl = 'https://pageviews.wmcloud.org/';
		$mwTitle = $this->titleFactory->newFromText( $title );
		$daysAgo = ComputedUserImpactLookup::PAGEVIEW_DAYS;
		$dtiAgo = new DateTime( '@' . strtotime( "-$daysAgo days" ) );
		$startDate = $dtiAgo->format( 'Y-m-d' );
		if ( $firstEditDate > $startDate ) {
			$startDate = $firstEditDate;
		}
		return wfAppendQuery( $baseUrl, [
			'project' => $this->AQSConfig->project,
			'userlang' => $this->contentLanguage->getCode(),
			'start' => $startDate,
			'end' => 'latest',
			'pages' => $mwTitle->getPrefixedDBkey(),
		] );
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
		];
	}

}
