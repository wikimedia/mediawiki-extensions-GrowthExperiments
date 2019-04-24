<?php

namespace GrowthExperiments\HelpPanel;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentor;
use MWException;

class MentorshipModuleQuestionPoster extends QuestionPoster {

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeader() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-mentorship-question-subject' )
			->plaintextParams( $this->getContext()->getUser()->getName() )
			->inContentLanguage()->text();
	}

	/**
	 * @inheritDoc
	 * @throws MWException If there's anything wrong with the current user's mentor
	 * @throws \ConfigException
	 */
	protected function getTargetTitle() {
		$mentor = Mentor::newFromMentee( $this->getContext()->getUser() );
		if ( !$mentor ) {
			throw new MWException( "Mentor not found" );
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
