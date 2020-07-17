<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use ConfigException;
use GrowthExperiments\HelpPanel;

/**
 * QuestionPoster variant for asking questions on the wiki's help desk.
 */
class HelpdeskQuestionPoster extends QuestionPoster {

	const QUESTION_PREF = 'growthexperiments-helppanel-questions';

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return HelpPanel::HELPDESK_QUESTION_TAG;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeaderTemplate() {
		return $this->relevantTitle ?
			$this->getContext()
				->msg( 'growthexperiments-help-panel-question-subject-template-with-title' )
				->params( $this->relevantTitle )
				->inContentLanguage()->text() :
			$this->getContext()
				->msg( 'growthexperiments-help-panel-question-subject-template' )
				->inContentLanguage()->text();
	}

	/**
	 * @inheritDoc
	 * @throws ConfigException
	 */
	protected function getTargetTitle() {
		return HelpPanel::getHelpDeskTitle( $this->getContext()->getConfig() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return self::QUESTION_PREF;
	}
}
