<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\IMentorManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use Wikimedia\Assert\Assert;
use Wikimedia\Stats\StatsFactory;

/**
 * QuestionPoster base class for asking a question from the assigned mentor.
 */
abstract class MentorQuestionPoster extends QuestionPoster {

	protected IMentorManager $mentorManager;
	protected MentorStatusManager $mentorStatusManager;

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		IMentorManager $mentorManager,
		MentorStatusManager $mentorStatusManager,
		PermissionManager $permissionManager,
		StatsFactory $statsFactory,
		bool $confirmEditInstalled,
		bool $flowInstalled,
		IContextSource $context,
		string $body,
		string $relevantTitleRaw = ''
	) {
		$this->mentorManager = $mentorManager;
		$this->mentorStatusManager = $mentorStatusManager;
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
	 */
	protected function getDirectTargetTitle() {
		$mentor = $this->mentorManager->getEffectiveMentorForUserSafe( $this->getContext()->getUser() );
		// TODO: This is actually not guaranteed by anything; invalid API calls will violate this
		// condition. See T386567.
		Assert::invariant( $mentor !== null, 'MentorQuestionPoster called without a mentor present' );
		return User::newFromIdentity( $mentor->getUserIdentity() )->getTalkPage();
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return Mentorship::QUESTION_PREF;
	}

	/**
	 * @inheritDoc
	 */
	protected function prependContent( string $body ): string {
		$primaryMentor = $this->mentorManager->getMentorForUserSafe( $this->getContext()->getUser() );
		$mentorStatus = $this->mentorStatusManager->getMentorStatus( $primaryMentor->getUserIdentity() );
		if ( $mentorStatus === MentorStatusManager::STATUS_AWAY ) {
			$mentorUsername = $primaryMentor->getUserIdentity()->getName();
			$menteeUsername = $this->getContext()->getUser()->getName();
			return $this->addAwayDisclaimer( $body, $mentorUsername, $menteeUsername );
		}
		return $body;
	}

	/**
	 * Add a disclaimer informing the question is posted to a different mentor
	 * because the primary one is currently away.
	 */
	private function addAwayDisclaimer( string $body, string $mentorUsername, string $menteeUsername ): string {
		$disclaimer = $this->getAwayDisclaimerText( $mentorUsername, $menteeUsername );
		return ":''" . $disclaimer . "''\n" . $body;
	}

	private function getAwayDisclaimerText( string $mentorUsername, string $menteeUsername ): string {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-mentorship-question-disclaimer-mentor-away' )
			->plaintextParams( $menteeUsername, $mentorUsername )
			->inContentLanguage()->plain();
	}
}
