<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\WikiConfigException;
use IContextSource;
use UserNotLoggedIn;

/**
 * QuestionPoster base class for asking a question from the assigned mentor.
 */
abstract class MentorQuestionPoster extends QuestionPoster {

	/** @var MentorManager */
	protected $mentorManager;

	/**
	 * @param MentorManager $mentorManager
	 * @param IContextSource $context
	 * @param string $body
	 * @param string $relevantTitle
	 * @throws UserNotLoggedIn
	 */
	public function __construct(
		MentorManager $mentorManager,
		IContextSource $context,
		$body,
		$relevantTitle = ''
	) {
		$this->mentorManager = $mentorManager;
		parent::__construct( $context, $body, $relevantTitle );
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeaderTemplate() {
		return $this->relevantTitle ?
			$this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-question-subject-with-title' )
				->plaintextParams( $this->getContext()->getUser()->getName() )
				->params( $this->relevantTitle )
				->inContentLanguage()->text() :
			$this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-question-subject' )
				->plaintextParams( $this->getContext()->getUser()->getName() )
				->inContentLanguage()->text();
	}

	/**
	 * @inheritDoc
	 * @throws WikiConfigException If there's anything wrong with the current user's mentor
	 */
	protected function getTargetTitle() {
		$mentor = $this->mentorManager->getMentorForUser( $this->getContext()->getUser() );
		return $mentor->getMentorUser()->getTalkPage();
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return Mentorship::QUESTION_PREF;
	}
}
