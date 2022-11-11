<?php

namespace GrowthExperiments\Rest\Handler;

use Config;
use DateTime;
use Exception;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use IBufferingStatsdDataFactory;
use Language;
use MediaWiki\MainConfigNames;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserTimeCorrection;
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
	private UserOptionsLookup $userOptionsLookup;
	private TitleFactory $titleFactory;
	private Language $contentLanguage;
	private IBufferingStatsdDataFactory $statsdDataFactory;
	private UserImpactStore $userImpactStore;

	/**
	 * @param Config $config
	 * @param stdClass $AQSConfig
	 * @param UserImpactStore $userImpactStore
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param TitleFactory $titleFactory
	 * @param Language $contentLanguage
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	public function __construct(
		Config $config,
		stdClass $AQSConfig,
		UserImpactStore $userImpactStore,
		UserOptionsLookup $userOptionsLookup,
		TitleFactory $titleFactory,
		Language $contentLanguage,
		IBufferingStatsdDataFactory $statsdDataFactory
	) {
		$this->config = $config;
		$this->AQSConfig = $AQSConfig;
		$this->userImpactStore = $userImpactStore;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->titleFactory = $titleFactory;
		$this->contentLanguage = $contentLanguage;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	/**
	 * @param UserIdentity $user
	 * @return array
	 * @throws HttpException
	 */
	public function run( UserIdentity $user ) {
		$start = microtime( true );
		$userImpact = $this->userImpactStore->getExpensiveUserImpact( $user );
		if ( !$userImpact ) {
			throw new HttpException( 'Impact data not found for user', 404 );
		}
		$json = $userImpact->jsonSerialize();
		foreach ( $json['dailyArticleViews'] as $title => $articleData ) {
			$json['dailyArticleViews'][$title]['pageviewsUrl'] = $this->getPageViewToolsUrl( $title, $user );
		}
		$this->statsdDataFactory->timing(
			'timing.growthExperiments.UserImpactHandler.run', microtime( true ) - $start
		);
		return $json;
	}

	/**
	 * @param string $title
	 * @param UserIdentity $user
	 * @throws Exception
	 * @return string Full URL for the PageViews tool for the given title and start date
	 */
	private function getPageViewToolsUrl( string $title, UserIdentity $user ): string {
		$baseUrl = 'https://pageviews.wmcloud.org/';
		$mwTitle = $this->titleFactory->newFromText( $title );
		$daysAgo = ComputedUserImpactLookup::PAGEVIEW_DAYS;
		$dtiAgo = new DateTime( '@' . strtotime( "-$daysAgo days" ) );
		$timeCorrection = new UserTimeCorrection(
			$this->userOptionsLookup->getOption( $user, 'timecorrection' ),
			$dtiAgo,
			$this->config->get( MainConfigNames::LocalTZoffset )
		);
		// TODO: add $timeCorrection->getTimeOffset() to $dtiAgo before format?
		return wfAppendQuery( $baseUrl, [
			'project' => $this->AQSConfig->project,
			'userlang' => $this->contentLanguage->getCode(),
			'start' => $dtiAgo->format( 'Y-m-d' ),
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
