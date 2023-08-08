<?php

namespace GrowthExperiments\Specials;

use ErrorPageError;
use FormSpecialPage;
use GrowthExperiments\Mentorship\MentorRemover;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use HTMLForm;
use PermissionsError;
use User;

class SpecialQuitMentorship extends FormSpecialPage {

	private MentorProvider $mentorProvider;
	private MentorRemover $mentorRemover;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param MentorRemover $mentorRemover
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		MentorRemover $mentorRemover
	) {
		parent::__construct( 'QuitMentorship', '', false );

		$this->mentorProvider = $mentorProvider;
		$this->mentorRemover = $mentorRemover;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
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
		return $this->msg( 'growthexperiments-quit-mentorship-title' );
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
		$this->requireNamedUser();

		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
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
		$form->setSubmitDestructive();
		$form->setSubmitText( $this->msg(
			'growthexperiments-quit-mentorship-reassign-mentees-confirm'
		)->text() );
	}

	/**
	 * @inheritDoc
	 */
	protected function preHtml() {
		return $this->msg(
			'growthexperiments-quit-mentorship-has-mentees-pretext'
		)->parseAsBlock();
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		return $this->mentorRemover->removeMentor(
			$this->getUser(),
			$this->getUser(),
			$data['reason'],
			$this->getContext()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-quit-mentorship-success' );
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		return $this->mentorProvider->isMentor( $this->getUser() );
	}

	/**
	 * @inheritDoc
	 */
	protected function displayRestrictionError() {
		throw new PermissionsError(
			null,
			[ 'growthexperiments-quit-mentorship-error-not-a-mentor' ]
		);
	}
}
