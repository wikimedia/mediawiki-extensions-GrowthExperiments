<?php

namespace GrowthExperiments\HelpPanel;

use ConfigException;
use GrowthExperiments\HelpPanel;

class HelpPanelQuestionPoster extends QuestionPoster {

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return HelpPanel::HELP_PANEL_QUESTION_TAG;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeader() {
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
}
