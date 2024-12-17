<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use UserNotLoggedIn;
use Wikimedia\Stats\StatsFactory;

/**
 * QuestionPoster base class for asking a question from the assigned mentor.
 */
abstract class MentorQuestionPoster extends QuestionPoster {

	/** @var MentorManager */
	protected $mentorManager;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param MentorManager $mentorManager
	 * @param PermissionManager $permissionManager
	 * @param StatsFactory $statsFactory
	 * @param bool $confirmEditInstalled
	 * @param bool $flowInstalled
	 * @param IContextSource $context
	 * @param string $body
	 * @param string $relevantTitleRaw
	 * @throws UserNotLoggedIn
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		MentorManager $mentorManager,
		PermissionManager $permissionManager,
		StatsFactory $statsFactory,
		bool $confirmEditInstalled,
		bool $flowInstalled,
		IContextSource $context,
		$body,
		$relevantTitleRaw = ''
	) {
		$this->mentorManager = $mentorManager;
		parent::__construct(
			$wikiPageFactory,
			$titleFactory,
			$permissionManager,
			$statsFactory,
			$confirmEditInstalled,
			$flowInstalled,
			$context,
			$body,
			$relevantTitleRaw
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeaderTemplate() {
		return $this->getWikitextLinkTarget() ?
			$this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-question-subject-with-title' )
				->plaintextParams( $this->getContext()->getUser()->getName() )
				->params( $this->getWikitextLinkTarget() )
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
		$mentor = $this->mentorManager->getEffectiveMentorForUser( $this->getContext()->getUser() );
		return User::newFromIdentity( $mentor->getUserIdentity() )->getTalkPage();
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return Mentorship::QUESTION_PREF;
	}
}
