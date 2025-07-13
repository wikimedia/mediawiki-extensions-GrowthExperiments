<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Exception\UserNotLoggedIn;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiHelpPanelPostQuestion extends ApiBase {

	public const API_PARAM_BODY = 'body';
	public const API_PARAM_SOURCE = 'source';
	public const API_PARAM_RELEVANT_TITLE = 'relevanttitle';

	/** API name => [ source, target ] using the respective QuestionPosterFactory constants */
	private const QUESTION_POSTER_TYPES = [
		'helpdesk' =>
			[ QuestionPosterFactory::SOURCE_HELP_PANEL, QuestionPosterFactory::TARGET_HELPDESK ],
		'mentor-homepage' =>
			[ QuestionPosterFactory::SOURCE_MENTORSHIP_MODULE, QuestionPosterFactory::TARGET_MENTOR_TALK ],
		'mentor-helppanel' =>
			[ QuestionPosterFactory::SOURCE_HELP_PANEL, QuestionPosterFactory::TARGET_MENTOR_TALK ],
		// old names (FIXME remove once not in use)
		'helppanel' =>
			[ QuestionPosterFactory::SOURCE_HELP_PANEL, QuestionPosterFactory::TARGET_HELPDESK ],
		'homepage-mentorship' =>
			[ QuestionPosterFactory::SOURCE_MENTORSHIP_MODULE, QuestionPosterFactory::TARGET_MENTOR_TALK ],
	];
	private QuestionPosterFactory $questionPosterFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param QuestionPosterFactory $questionPosterFactory
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		QuestionPosterFactory $questionPosterFactory
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->questionPosterFactory = $questionPosterFactory;
	}

	/**
	 * Save help panel question post.
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$questionPoster = $this->getQuestionPoster(
			$params[self::API_PARAM_SOURCE],
			$params[self::API_PARAM_BODY],
			$params[self::API_PARAM_RELEVANT_TITLE] ?? ''
		);

		if ( $params[self::API_PARAM_RELEVANT_TITLE] ) {
			$status = $questionPoster->validateRelevantTitle();
			if ( !$status->isGood() ) {
				$this->dieStatus( $status );
			}
		}

		$status = $questionPoster->submit();
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		$result = [
			'result' => 'success',
			'revision' => $questionPoster->getRevisionId(),
			'isfirstedit' => (int)$questionPoster->isFirstEdit(),
			'viewquestionurl' => $questionPoster->getResultUrl(),
			'source' => $params[self::API_PARAM_SOURCE]
		];

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param string $source
	 * @param string $body
	 * @param string|null $relevantTitle
	 * @return QuestionPoster
	 * @throws ApiUsageException
	 */
	private function getQuestionPoster( $source, $body, $relevantTitle ): QuestionPoster {
		$questionPosterType = self::QUESTION_POSTER_TYPES[$source];
		try {
			return $this->questionPosterFactory->getQuestionPoster(
				$questionPosterType[0],
				$questionPosterType[1],
				$this->getContext(),
				$body,
				$relevantTitle ?? ''
			);
		} catch ( UserNotLoggedIn ) {
			throw ApiUsageException::newWithMessage( $this,
				'apierror-mustbeloggedin-helppanelquestionposter', 'notloggedin' );
		}
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
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_CHARS => 2000,
			],
			self::API_PARAM_SOURCE => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => array_keys( self::QUESTION_POSTER_TYPES ),
				ParamValidator::PARAM_DEFAULT => 'helpdesk',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			self::API_PARAM_RELEVANT_TITLE => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'string',
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
