<?php

namespace GrowthExperiments\Api;

use ApiBase;
use FormatJson;
use GrowthExperiments\WelcomeSurvey;

class ApiWelcomeSurveyHandleResponses extends ApiBase {

	/**
	 * Save survey responses or record skip as appropriate.
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$welcomeSurvey = new WelcomeSurvey( $this->getContext() );
		$welcomeSurvey->handleResponses(
			FormatJson::decode( $params[ 'responses' ], true ),
			$params[ 'surveyaction' ] === 'save',
			$params[ 'group' ],
			wfTimestamp( TS_MW, substr( $params[ 'rendertimestamp' ], 0, 10 ) )
		);

		$result = [ 'result' => 'success' ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'surveyaction' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string',
			],
			'group' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string',
			],
			'rendertimestamp' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer',
			],
			'responses' => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'string'
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}
}
