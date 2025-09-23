<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\WelcomeSurveyFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
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
		$body = $this->getValidatedBody();
		'@phan-var array $body';

		if ( !$this->getSession()->getToken( 'welcomesurvey' )->match( $body['token'] ) ) {
			throw new HttpException( 'Invalid token', 400 );
		}

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( RequestContext::getMain() );
		$welcomeSurvey->dismiss();

		return [ 'success' => true ];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'token' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * Support x-www-form-urlencoded (and nothing else), as required by RFC 6749.
	 * @return string[]
	 */
	public function getSupportedRequestTypes(): array {
		return [
			'application/x-www-form-urlencoded',
		];
	}

}
