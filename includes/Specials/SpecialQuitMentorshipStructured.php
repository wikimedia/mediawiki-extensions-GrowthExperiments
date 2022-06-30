<?php

namespace GrowthExperiments\Specials;

use ErrorPageError;
use FormSpecialPage;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\QuitMentorshipFactory;
use GrowthExperiments\Mentorship\Store\MentorStore;
use HTMLForm;
use Status;

class SpecialQuitMentorshipStructured extends FormSpecialPage {

	/** @var QuitMentorshipFactory */
	private $quitMentorshipFactory;

	/** @var MentorStore */
	private $mentorStore;

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var IMentorWriter */
	private $mentorWriter;

	/** @var bool Are there any mentees to reassign? */
	private $hasMentees;

	/**
	 * @param QuitMentorshipFactory $quitMentorshipFactory
	 * @param MentorStore $mentorStore
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 */
	public function __construct(
		QuitMentorshipFactory $quitMentorshipFactory,
		MentorStore $mentorStore,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter
	) {
		parent::__construct( 'QuitMentorship', '', false );

		$this->quitMentorshipFactory = $quitMentorshipFactory;
		$this->mentorStore = $mentorStore;
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
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
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireMentorDashboardEnabled();
		$this->requireLogin();

		$this->hasMentees = $this->mentorStore->hasAnyMentees(
			$this->getUser(),
			MentorStore::ROLE_PRIMARY
		);
		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		if ( !$this->hasMentees ) {
			return [];
		}

		return [
			'reason' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-quit-mentorship-reason'
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		if ( !$this->hasMentees ) {
			$form->suppressDefaultSubmit();
		}

		$form->setSubmitText( $this->msg(
			'growthexperiments-quit-mentorship-reassign-mentees-confirm'
		)->text() );
	}

	/**
	 * @inheritDoc
	 */
	protected function preHtml() {
		if ( !$this->hasMentees ) {
			return $this->msg(
				'growthexperiments-quit-mentorship-no-mentees'
			)->parseAsBlock();
		} else {
			return $this->msg(
				'growthexperiments-quit-mentorship-has-mentees-pretext'
			)->parseAsBlock();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->mentorWriter->removeMentor(
			$this->mentorProvider->newMentorFromUserIdentity( $this->getUser() ),
			$this->getUser(),
			$data['reason']
		);
		// reassignMentees() will submit a job
		$this->quitMentorshipFactory->newQuitMentorship(
			$this->getUser(),
			$this->getContext()
		)->reassignMentees(
			'growthexperiments-quit-mentorship-reassign-mentees-log-message'
		);
		return Status::newGood();
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-quit-mentorship-success' );
	}
}
