<?php

namespace GrowthExperiments\Specials;

use ErrorPageError;
use FormSpecialPage;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\QuitMentorship;
use GrowthExperiments\Mentorship\QuitMentorshipFactory;
use HTMLForm;
use Status;

class SpecialQuitMentorshipWikitext extends FormSpecialPage {

	/** @var QuitMentorshipFactory */
	private $quitMentorshipFactory;

	/** @var QuitMentorship */
	private $quitMentorship;

	/** @var int One of QuitMentorship::STAGE_* constants */
	private $quitMentorshipStage;

	/** @var MentorProvider */
	private $mentorProvider;

	/**
	 * @param QuitMentorshipFactory $quitMentorshipFactory
	 * @param MentorProvider $mentorProvider
	 */
	public function __construct(
		QuitMentorshipFactory $quitMentorshipFactory,
		MentorProvider $mentorProvider
	) {
		parent::__construct( 'QuitMentorship', '', false );
		$this->quitMentorshipFactory = $quitMentorshipFactory;
		$this->mentorProvider = $mentorProvider;
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
		if ( !$this->mentorProvider->getSignupTitle() ) {
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

		$this->quitMentorship = $this->quitMentorshipFactory->newQuitMentorship(
			$this->getUser(),
			$this->getContext()
		);
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

		$form->setSubmitDestructive();
		$form->setSubmitText( $this->msg(
			'growthexperiments-quit-mentorship-reassign-mentees-confirm'
		)->text() );
	}

	/**
	 * @inheritDoc
	 */
	protected function preHtml() {
		if ( $this->quitMentorshipStage === QuitMentorship::STAGE_LISTED_AS_MENTOR ) {
			return $this->msg(
				'growthexperiments-quit-mentorship-listed-as-mentor-pretext',
				$this->mentorProvider->getSignupTitle()->getPrefixedText()
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
