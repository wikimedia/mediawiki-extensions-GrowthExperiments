<?php

namespace GrowthExperiments\Api;

use ApiBase;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\RecentQuestionsFormatter;
use JsonSerializable;

class ApiQuestionStore extends ApiBase {

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$questions = QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			$params['storage']
		)->loadQuestionsAndUpdate();
		if ( !count( $questions ) ) {
			$result = [ 'html' => '', 'questions' => '' ];
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return;
		}

		$questionFormatter = new RecentQuestionsFormatter(
			$this->getContext(),
			$questions,
			$params['storage']
		);
		$result = [
			'html' => $questionFormatter->formatResponses(),
			'questions' => array_map( function ( JsonSerializable $question ) {
				return $question->jsonSerialize();
			}, $questions )
		];

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$result
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'storage' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [ Mentorship::QUESTION_PREF, Help::QUESTION_PREF ]
			],
		];
	}
}
