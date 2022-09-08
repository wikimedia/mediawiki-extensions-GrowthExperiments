<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\WelcomeSurveyFactory;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handle POST requests to /growthexperiments/v0/welcomesurvey/skip
 *
 * Dismisses the welcome survey (in practice, used to dismiss reminder notices about the survey).
 *
 * As a no-JS fallback Special:WelcomeSurvey/skip is used.
 */
class WelcomeSurveySkipHandler extends Handler {

	/** @var WelcomeSurveyFactory */
	private $welcomeSurveyFactory;

	/**
	 * @param WelcomeSurveyFactory $welcomeSurveyFactory
	 */
	public function __construct(
		WelcomeSurveyFactory $welcomeSurveyFactory
	) {
		$this->welcomeSurveyFactory = $welcomeSurveyFactory;
	}

	/**
	 * @return array|Response
	 * @throws HttpException
	 */
	public function execute() {
		$params = $this->getValidatedParams();
		if ( !$this->getSession()->getToken( 'welcomesurvey' )->match( $params['token'] ) ) {
			throw new HttpException( 'Invalid token', 400 );
		}

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( RequestContext::getMain() );
		$welcomeSurvey->dismiss();

		return [ 'success' => true ];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'token' => [
				self::PARAM_SOURCE => 'post',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
