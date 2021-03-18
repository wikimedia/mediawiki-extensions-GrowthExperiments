<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\WikiConfigException;
use IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use User;
use UserNotLoggedIn;

/**
 * QuestionPoster base class for asking a question from the assigned mentor.
 */
abstract class MentorQuestionPoster extends QuestionPoster {

	/** @var MentorManager */
	protected $mentorManager;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param MentorManager $mentorManager
	 * @param PermissionManager $permissionManager
	 * @param IContextSource $context
	 * @param string $body
	 * @param string $relevantTitle
	 * @throws UserNotLoggedIn
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		MentorManager $mentorManager,
		PermissionManager $permissionManager,
		IContextSource $context,
		$body,
		$relevantTitle = ''
	) {
		$this->mentorManager = $mentorManager;
		parent::__construct( $wikiPageFactory, $permissionManager, $context, $body, $relevantTitle );
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
	protected function getDirectTargetTitle() {
		$mentor = $this->mentorManager->getMentorForUser( $this->getContext()->getUser() );
		return User::newFromIdentity( $mentor->getMentorUser() )->getTalkPage();
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return Mentorship::QUESTION_PREF;
	}
}
