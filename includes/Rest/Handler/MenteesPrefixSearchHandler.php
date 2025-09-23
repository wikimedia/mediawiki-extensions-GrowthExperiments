<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewPrefixSearchDataFilter;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class MenteesPrefixSearchHandler extends SimpleHandler {

	private MenteeOverviewDataProvider $dataProvider;

	public function __construct(
		MenteeOverviewDataProvider $dataProvider
	) {
		$this->dataProvider = $dataProvider;
	}

	/**
	 * @param string $prefix
	 * @return array
	 * @throws HttpException
	 */
	public function run( string $prefix ) {
		$authority = $this->getAuthority();
		if ( !$authority->isNamed() ) {
			throw new HttpException( 'You must be logged in', 403 );
		}
		$user = $authority->getUser();

		$params = $this->getValidatedParams();
		$limit = $params['limit'] ?? 10;

		$dataFilter = new MenteeOverviewPrefixSearchDataFilter(
			$this->dataProvider->getFormattedDataForMentor( $user )
		);

		return [
			'prefix' => $prefix,
			'limit' => $limit,
			'usernames' => $dataFilter
				->prefix( $prefix )
				->limit( $limit )
				->getUsernames(),
		];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'prefix' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
