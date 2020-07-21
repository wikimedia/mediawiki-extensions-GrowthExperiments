<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiUsageException;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster;
use MWException;

class ApiHelpPanelPostQuestion extends ApiBase {

	const API_PARAM_BODY = 'body';
	const API_PARAM_SOURCE = 'source';
	const API_PARAM_RELEVANT_TITLE = 'relevanttitle';

	private const QUESTION_POSTER_CLASSES = [
		'helpdesk' => HelpdeskQuestionPoster::class,
		'mentor-homepage' => MentorQuestionPoster::class,
		// old names (FIXME remove once not in use)
		'helppanel' => HelpdeskQuestionPoster::class,
		'homepage-mentorship' => MentorQuestionPoster::class,
	];

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
		$this->setQuestionPoster(
			$params[self::API_PARAM_SOURCE],
			$params[self::API_PARAM_BODY],
			$params[self::API_PARAM_RELEVANT_TITLE]
		);

		if ( $params[self::API_PARAM_RELEVANT_TITLE] ) {
			$status = $this->questionPoster->validateRelevantTitle();
			if ( !$status->isGood() ) {
				$this->dieStatus( $status );
			}
		}

		$status = $this->questionPoster->submit();
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		$result = [
			'result' => 'success',
			'revision' => $this->questionPoster->getRevisionId(),
			'isfirstedit' => (int)$this->questionPoster->isFirstEdit(),
			'viewquestionurl' => $this->questionPoster->getResultUrl(),
			'source' => $params[self::API_PARAM_SOURCE]
		];

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param string $source
	 * @param string $body
	 * @param string $relevantTitle
	 * @throws MWException
	 */
	private function setQuestionPoster( $source, $body, $relevantTitle ) {
		$questionPosterClass = self::QUESTION_POSTER_CLASSES[$source];
		$this->questionPoster = new $questionPosterClass(
			$this->getContext(),
			$body,
			$relevantTitle
		);
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function isInternal() {
		// For use by the question poster dialog only. All functionality available via core APIs.
		return true;
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
				ApiBase::PARAM_TYPE => array_keys( self::QUESTION_POSTER_CLASSES ),
				ApiBase::PARAM_DFLT => 'helpdesk',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			self::API_PARAM_RELEVANT_TITLE => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'string',
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
