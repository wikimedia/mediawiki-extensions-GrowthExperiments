<?php

namespace GrowthExperiments\Api;

use ApiBase;
use GrowthExperiments\HelpPanel\QuestionPoster\HelpdeskQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
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
		$questionFormatter = new RecentQuestionsFormatter(
			$this->getContext(),
			$questions,
			$params['storage']
		);
		$result = [
			'html' => $questionFormatter->format(),
			'questions' => array_map( static function ( JsonSerializable $question ) {
				return $question->jsonSerialize();
			}, $questions ) ?: []
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
				ApiBase::PARAM_TYPE => [ Mentorship::QUESTION_PREF, HelpdeskQuestionPoster::QUESTION_PREF ]
			],
		];
	}
}
