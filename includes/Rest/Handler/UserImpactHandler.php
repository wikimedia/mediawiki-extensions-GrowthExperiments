<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handler for the GET /growthexperiments/v0/user-impact/{user} endpoint.
 * Returns data about the user's impact on the wiki.
 */
class UserImpactHandler extends SimpleHandler {

	/** @var UserImpactLookup */
	private $userImpactLookup;

	/**
	 * @param UserImpactLookup $userImpactLookup
	 */
	public function __construct(
		UserImpactLookup $userImpactLookup
	) {
		$this->userImpactLookup = $userImpactLookup;
	}

	/**
	 * @param UserIdentity $user
	 * @return array
	 * @throws HttpException
	 */
	public function run( UserIdentity $user ) {
		$useLatest = $this->getValidatedParams()['useLatest'] ?? false;
		$userImpact = $this->userImpactLookup->getUserImpact( $user, $useLatest );
		if ( !$userImpact ) {
			throw new HttpException( 'Impact data not found for user', 404 );
		}
		return $userImpact->jsonSerialize();
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
			'latest' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
			],
		];
	}

}
