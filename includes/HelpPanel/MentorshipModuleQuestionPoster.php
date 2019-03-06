<?php

namespace GrowthExperiments\HelpPanel;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentor;
use MWException;
use Title;

class MentorshipModuleQuestionPoster extends QuestionPoster {

	/**
	 * {@inheritDoc}
	 */
	public function addTag() {
		$this->pageUpdater->addTag( Mentorship::MENTORSHIP_MODULE_QUESTION_TAG );
	}

	/**
	 * @param string $relevantTitle
	 */
	public function setSectionHeader( $relevantTitle ) {
		$this->sectionHeader = $this->context
			->msg( 'growthexperiments-homepage-mentorship-question-subject' )
			->plaintextParams( $this->context->getUser()->getName() )
			->inContentLanguage()->text();
	}

	/**
	 * @return Title Talk page of the current user's mentor
	 * @throws MWException If there's anything wrong with the current user's mentor
	 * @throws \ConfigException
	 */
	protected function getTargetTitle() {
		$mentor = Mentor::newFromMentee( $this->context->getUser() );
		if ( !$mentor ) {
			throw new MWException( "Mentor not found" );
		}
		return $mentor->getMentorUser()->getTalkPage();
	}
}
