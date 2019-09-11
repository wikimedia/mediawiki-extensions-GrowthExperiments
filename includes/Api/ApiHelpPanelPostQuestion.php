<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use ApiUsageException;
use GrowthExperiments\HelpPanel\HelpPanelQuestionPoster;
use GrowthExperiments\HelpPanel\MentorshipModuleQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionPoster;
use MediaWiki\Logger\LoggerFactory;
use MWException;
use Status;

class ApiHelpPanelPostQuestion extends ApiBase {

	const API_PARAM_BODY = 'body';
	const API_PARAM_SOURCE = 'source';
	const API_PARAM_EMAIL = 'email';
	const API_PARAM_RESEND_CONFIRMATION = 'resendconfirmation';
	const API_PARAM_RELEVANT_TITLE = 'relevanttitle';

	/**
	 * @var QuestionPoster
	 */
	private $questionPoster;

	/**
	 * @var array [ 'source' => ClassName::class ] of the registered question posters
	 */
	private $questionPosterClasses;

	/**
	 * @inheritDoc
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$this->questionPosterClasses = [
			'helppanel' => HelpPanelQuestionPoster::class,
			'homepage-mentorship' => MentorshipModuleQuestionPoster::class,
		];
	}

	/**
	 * Save help panel question post.
	 *
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$user = $this->getUser();
		$emailStatus = null;
		$this->setQuestionPoster(
			$params[self::API_PARAM_SOURCE],
			$params[self::API_PARAM_BODY],
			$params[self::API_PARAM_RELEVANT_TITLE]
		);

		if ( $params[self::API_PARAM_RELEVANT_TITLE] ) {
			$status = $this->questionPoster->validateRelevantTitle();
			if ( !$status->isGood() ) {
				throw new ApiUsageException( null, $status );
			}
		}

		$status = $this->questionPoster->submit();
		if ( !$status->isGood() ) {
			throw new ApiUsageException( null, $status );
		}

		$result = [
			'result' => 'success',
			'revision' => $this->questionPoster->getRevisionId(),
			'isfirstedit' => (int)$this->questionPoster->isFirstEdit(),
			'viewquestionurl' => $this->questionPoster->getResultUrl(),
			'email' => null,
			'source' => $params[self::API_PARAM_SOURCE]
		];

		if ( $params[self::API_PARAM_EMAIL] ?? false ) {
			$emailStatus = $this->questionPoster->handleEmail( $params[self::API_PARAM_EMAIL] );
		} elseif ( $params[self::API_PARAM_RESEND_CONFIRMATION] ) {
			$userLatest = $user->getInstanceForUpdate();
			if ( $userLatest->isEmailConfirmed() ) {
				$emailStatus = Status::newGood( 'already_confirmed' );
			} else {
				$emailStatus = $userLatest->sendConfirmationMail();
				if ( $emailStatus->isGood() ) {
					// Make the result readable in the API response; default value
					// is null for success.
					$emailStatus->setResult( true, 'send_confirm' );
				}
			}
		} else {
			$emailStatus = Status::newGood( 'no_op' );
		}
		$result['email'] = $emailStatus->getValue();

		// If email handling fails, log a message but don't cause the request
		// to fail; overwrite status with error message.
		if ( !$emailStatus->isGood() ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )
				->error( 'Email handling failed for user ID {user}: {status}', [
					'user' => $user->getId(),
					'status' => $emailStatus->getWikiText()
				] );
			$result['email'] = $emailStatus->getWikiText();
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param string $source
	 * @param $body
	 * @param string $relevantTitle
	 * @throws ApiUsageException
	 */
	private function setQuestionPoster( $source, $body, $relevantTitle ) {
		try {
			$questionPosterClass = $this->questionPosterClasses[$source];
			$this->questionPoster = new $questionPosterClass(
				$this->getContext(),
				$body,
				$relevantTitle
			);
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
				ApiBase::PARAM_MAX_CHARS => 2000,
			],
			self::API_PARAM_SOURCE => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => array_keys( $this->questionPosterClasses ),
				ApiBase::PARAM_DFLT => 'helppanel',
			],
			self::API_PARAM_RELEVANT_TITLE => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'string',
			],
			self::API_PARAM_EMAIL => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'string',
			],
			self::API_PARAM_RESEND_CONFIRMATION => false,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}
}
