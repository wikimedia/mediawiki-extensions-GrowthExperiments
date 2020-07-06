<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentor;
use GrowthExperiments\WikiConfigException;

class MentorQuestionPoster extends QuestionPoster {

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeaderTemplate() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-mentorship-question-subject' )
			->plaintextParams( $this->getContext()->getUser()->getName() )
			->inContentLanguage()->text();
	}

	/**
	 * @inheritDoc
	 * @throws WikiConfigException If there's anything wrong with the current user's mentor
	 */
	protected function getTargetTitle() {
		$mentor = Mentor::newFromMentee( $this->getContext()->getUser() );
		if ( !$mentor ) {
			throw new WikiConfigException( "Mentor not found" );
		}
		return $mentor->getMentorUser()->getTalkPage();
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return Mentorship::QUESTION_PREF;
	}
}
