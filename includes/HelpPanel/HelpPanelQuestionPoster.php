<?php

namespace GrowthExperiments\HelpPanel;

use ConfigException;
use GrowthExperiments\HelpPanel;
use Title;

class HelpPanelQuestionPoster extends QuestionPoster {

	/**
	 * {@inheritDoc}
	 */
	public function addTag() {
		$this->pageUpdater->addTag( HelpPanel::HELP_PANEL_QUESTION_TAG );
	}

	/**
	 * @param string $relevantTitle
	 */
	public function setSectionHeader( $relevantTitle ) {
		if ( $relevantTitle ) {
			$this->sectionHeader = $this->context
				->msg( 'growthexperiments-help-panel-question-subject-template-with-title' )
				->params( $relevantTitle )
				->inContentLanguage()->text();
		} else {
			$this->sectionHeader = $this->context
				->msg( 'growthexperiments-help-panel-question-subject-template' )
				->inContentLanguage()->text();
		}
	}

	/**
	 * @return Title
	 * @throws ConfigException
	 */
	protected function getTargetTitle() {
		return HelpPanel::getHelpDeskTitle( $this->context->getConfig() );
	}
}
