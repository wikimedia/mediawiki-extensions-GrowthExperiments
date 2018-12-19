<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiUsageException;
use GrowthExperiments\HelpPanel\QuestionPoster;
use MediaWiki\Logger\LoggerFactory;

class ApiHelpPanelPostQuestion extends ApiBase {

	const API_PARAM_BODY = 'body';
	const API_PARAM_EMAIL = 'email';
	const API_PARAM_RELEVANT_TITLE = 'relevanttitle';

	/**
	 * Save help panel question post.
	 *
	 * @throws ApiUsageException
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$emailStatus = null;
		$questionPoster = new QuestionPoster( $this->getContext() );
		if ( $params[self::API_PARAM_RELEVANT_TITLE] ) {
			$status = $questionPoster->validateRelevantTitle( $params[self::API_PARAM_RELEVANT_TITLE] );
			if ( !$status->isGood() ) {
				throw new ApiUsageException( null, $status );
			}
		}

		$status = $questionPoster->submit(
			$params[self::API_PARAM_BODY],
			$params[self::API_PARAM_RELEVANT_TITLE]
		);
		if ( !$status->isGood() ) {
			throw new ApiUsageException( null, $status );
		}

		$result = [
			'result' => 'success',
			'revision' => $questionPoster->getRevisionId(),
			'isfirstedit' => (int)$questionPoster->isFirstEdit(),
			'viewquestionurl' => $questionPoster->getResultUrl(),
			'email' => null
		];

		$emailStatus = $questionPoster->handleEmail( $params[self::API_PARAM_EMAIL] );
		$result['email'] = $emailStatus->getValue();
		// If email handling fails, log a message but don't cause the request
		// to fail; overwrite status with error message.
		if ( !$emailStatus->isGood() ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )
				->error( 'Email handling failed for {user}: {status}', [
					'user' => $this->getUser()->getId(),
					'status' => $emailStatus->getWikiText()
				] );
			$result['email'] = $emailStatus->getWikiText();
		}

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
			self::API_PARAM_BODY => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_MAX_CHARS => 2000
			],
			self::API_PARAM_RELEVANT_TITLE => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'string',
			],
			self::API_PARAM_EMAIL => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}
}
