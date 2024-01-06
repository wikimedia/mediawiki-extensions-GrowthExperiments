<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel;
use MediaWiki\Config\ConfigException;
use MediaWiki\MediaWikiServices;

/**
 * QuestionPoster variant for asking questions on the wiki's help desk.
 */
class HelpdeskQuestionPoster extends QuestionPoster {

	public const QUESTION_PREF = 'growthexperiments-helppanel-questions';

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
		return $this->getWikitextLinkTarget() ?
			$this->getContext()
				->msg( 'growthexperiments-help-panel-question-subject-template-with-title' )
				->params( $this->getWikitextLinkTarget() )
				->inContentLanguage()->text() :
			$this->getContext()
				->msg( 'growthexperiments-help-panel-question-subject-template' )
				->inContentLanguage()->text();
	}

	/**
	 * @inheritDoc
	 * @throws ConfigException
	 */
	protected function getDirectTargetTitle() {
		return HelpPanel::getHelpDeskTitle(
			GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )
				->getGrowthWikiConfig()
			);
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return self::QUESTION_PREF;
	}
}
