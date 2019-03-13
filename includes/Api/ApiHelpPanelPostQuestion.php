<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiUsageException;
use GrowthExperiments\HelpPanel\HelpModuleQuestionPoster;
use GrowthExperiments\HelpPanel\HelpPanelQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionPoster;
use GrowthExperiments\HomepageHooks;
use MediaWiki\Logger\LoggerFactory;
use MWException;
use Title;

class ApiHelpPanelPostQuestion extends ApiBase {

	const API_PARAM_BODY = 'body';
	const API_PARAM_EMAIL = 'email';
	const API_PARAM_RELEVANT_TITLE = 'relevanttitle';

	/**
	 * @var QuestionPoster
	 */
	private $questionPoster;

	/**
	 * Save help panel question post.
	 *
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$emailStatus = null;
		$this->setQuestionPoster( $params[self::API_PARAM_RELEVANT_TITLE] );

		if ( $params[self::API_PARAM_RELEVANT_TITLE] ) {
			$status = $this->questionPoster->validateRelevantTitle(
				$params[self::API_PARAM_RELEVANT_TITLE] );
			if ( !$status->isGood() ) {
				throw new ApiUsageException( null, $status );
			}
		}

		$status = $this->questionPoster->submit(
			$params[self::API_PARAM_BODY],
			$params[self::API_PARAM_RELEVANT_TITLE]
		);
		if ( !$status->isGood() ) {
			throw new ApiUsageException( null, $status );
		}

		$result = [
			'result' => 'success',
			'revision' => $this->questionPoster->getRevisionId(),
			'isfirstedit' => (int)$this->questionPoster->isFirstEdit(),
			'viewquestionurl' => $this->questionPoster->getResultUrl(),
			'email' => null
		];

		$emailStatus = $this->questionPoster->handleEmail( $params[self::API_PARAM_EMAIL] );
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
	 * @param string $relevantTitle
	 * @throws ApiUsageException
	 */
	private function setQuestionPoster( $relevantTitle = '' ) {
		$title = Title::newFromText( $relevantTitle );
		if ( HomepageHooks::isHomepageEnabled( $this->getUser() ) && $title &&
			 $title->isSpecial( 'Homepage' )
		) {
			try {
				$this->questionPoster = new HelpModuleQuestionPoster( $this->getContext() );
				return;
			} catch ( \Exception $exception ) {
				$this->dieWithException( $exception );
			}
		}
		// If not the homepage, assume it's a help panel question.
		try {
			$this->questionPoster = new HelpPanelQuestionPoster( $this->getContext() );
		} catch ( \Exception $exception ) {
			$this->dieWithException( $exception );
		}
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
