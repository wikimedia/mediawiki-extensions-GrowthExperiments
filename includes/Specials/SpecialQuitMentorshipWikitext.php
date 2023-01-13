<?php

namespace GrowthExperiments\Specials;

use ErrorPageError;
use FormSpecialPage;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\ReassignMentees;
use GrowthExperiments\Mentorship\ReassignMenteesFactory;
use HTMLForm;
use Status;

class SpecialQuitMentorshipWikitext extends FormSpecialPage {

	/** @var ReassignMenteesFactory */
	private $reassignMenteesFactory;

	/** @var ReassignMentees */
	private $reassignMentees;

	/** @var int One of ReassignMentees::STAGE_* constants */
	private $reassignMenteesStage;

	/** @var MentorProvider */
	private $mentorProvider;

	/**
	 * @param ReassignMenteesFactory $reassignMenteesFactory
	 * @param MentorProvider $mentorProvider
	 */
	public function __construct(
		ReassignMenteesFactory $reassignMenteesFactory,
		MentorProvider $mentorProvider
	) {
		parent::__construct( 'QuitMentorship', '', false );
		$this->reassignMenteesFactory = $reassignMenteesFactory;
		$this->mentorProvider = $mentorProvider;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
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

		$this->reassignMentees = $this->reassignMenteesFactory->newReassignMentees(
			$this->getUser(),
			$this->getUser(),
			$this->getContext()
		);
		$this->reassignMenteesStage = $this->reassignMentees->getStage();
		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		if ( $this->reassignMenteesStage !== ReassignMentees::STAGE_NOT_LISTED_HAS_MENTEES ) {
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
		if ( $this->reassignMenteesStage === ReassignMentees::STAGE_LISTED_AS_MENTOR ) {
			return $this->msg(
				'growthexperiments-quit-mentorship-listed-as-mentor-pretext',
				$this->mentorProvider->getSignupTitle()->getPrefixedText()
			)->parseAsBlock();
		} elseif ( $this->reassignMenteesStage === ReassignMentees::STAGE_NOT_LISTED_HAS_MENTEES ) {
			return $this->msg(
				'growthexperiments-quit-mentorship-not-listed-has-mentees-pretext'
			)->parseAsBlock();
		} elseif ( $this->reassignMenteesStage === ReassignMentees::STAGE_NOT_LISTED_NO_MENTEES ) {
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
		$this->reassignMentees->reassignMentees(
			'growthexperiments-quit-mentorship-reassign-mentees-log-message'
		);

		return Status::newGood();
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-quit-mentorship-success' );
	}
}
