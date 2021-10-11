<?php

namespace GrowthExperiments\Specials;

use ErrorPageError;
use FormSpecialPage;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\QuitMentorship;
use GrowthExperiments\Mentorship\QuitMentorshipFactory;
use HTMLForm;
use Status;

class SpecialQuitMentorship extends FormSpecialPage {

	/** @var QuitMentorship */
	private $quitMentorship;

	/** @var int One of QuitMentorship::STAGE_* constants */
	private $quitMentorshipStage;

	/** @var MentorManager */
	private $mentorManager;

	/**
	 * @param QuitMentorshipFactory $quitMentorshipFactory
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		QuitMentorshipFactory $quitMentorshipFactory,
		MentorManager $mentorManager
	) {
		parent::__construct( 'QuitMentorship', '', false );

		$this->quitMentorship = $quitMentorshipFactory->newQuitMentorship(
			$this->getUser(),
			$this->getContext()
		);
		$this->mentorManager = $mentorManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessagePrefix() {
		return 'growthexperiments-quit-mentorship';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-quit-mentorship-title' )->text();
	}

	/**
	 * Check if mentor dashboard is enabled via GEMentorDashboardEnabled
	 *
	 * @return bool
	 */
	private function isEnabled(): bool {
		return $this->getConfig()->get( 'GEMentorDashboardEnabled' );
	}

	/**
	 * Ensure mentor dashboard feature flag is on
	 *
	 * @throws ErrorPageError
	 */
	private function requireMentorDashboardEnabled() {
		if ( !$this->isEnabled() ) {
			// Mentor dashboard is disabled, display a meaningful restriction error
			throw new ErrorPageError(
				'growthexperiments-quit-mentorship-title',
				'growthexperiments-quit-mentorship-disabled'
			);
		}
	}

	/**
	 * Ensure the automatic mentor list is configured
	 *
	 * @throws ErrorPageError if mentor list is missing
	 */
	private function requireMentorList() {
		if ( !$this->mentorManager->getAutoMentorsListTitle() ) {
			throw new ErrorPageError(
				'growthexperiments-quit-mentorship-title',
				'growthexperiments-quit-mentorship-misconfigured-missing-list'
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireMentorDashboardEnabled();
		$this->requireMentorList();
		$this->requireLogin();

		$this->quitMentorshipStage = $this->quitMentorship->getStage();
		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		if ( $this->quitMentorshipStage !== QuitMentorship::STAGE_NOT_LISTED_HAS_MENTEES ) {
			$form->suppressDefaultSubmit();
		}

		$form->setSubmitText( $this->msg(
			'growthexperiments-quit-mentorship-reassign-mentees-confirm'
		)->text() );
	}

	/**
	 * @inheritDoc
	 */
	protected function preText() {
		if ( $this->quitMentorshipStage === QuitMentorship::STAGE_LISTED_AS_MENTOR ) {
			return $this->msg(
				'growthexperiments-quit-mentorship-listed-as-mentor-pretext',
				$this->mentorManager->getAutoMentorsListTitle()->getPrefixedText()
			)->parseAsBlock();
		} elseif ( $this->quitMentorshipStage === QuitMentorship::STAGE_NOT_LISTED_HAS_MENTEES ) {
			return $this->msg(
				'growthexperiments-quit-mentorship-not-listed-has-mentees-pretext'
			)->parseAsBlock();
		} elseif ( $this->quitMentorshipStage === QuitMentorship::STAGE_NOT_LISTED_NO_MENTEES ) {
			return $this->msg(
				'growthexperiments-quit-mentorship-no-mentees'
			)->parseAsBlock();
		}

		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->quitMentorship->reassignMentees(
			'growthexperiments-quit-mentorship-reassign-mentees-log-message'
		);

		return Status::newGood();
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-quit-mentorship-success' );
	}
}
