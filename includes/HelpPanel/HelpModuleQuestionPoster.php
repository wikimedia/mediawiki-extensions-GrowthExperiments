<?php

namespace GrowthExperiments\HelpPanel;

use GrowthExperiments\HomepageModules\Help;

class HelpModuleQuestionPoster extends QuestionPoster {

	/**
	 * {@inheritDoc}
	 */
	public function addTag() {
		$this->pageUpdater->addTag( Help::HELP_MODULE_QUESTION_TAG );
	}

	/**
	 * @param string $relevantTitle
	 */
	public function setSectionHeader( $relevantTitle ) {
		$this->sectionHeader = $this->context
			->msg( 'growthexperiments-help-panel-question-subject-template-from-homepage' )
			->inContentLanguage()->text();
	}
}
