<?php

namespace GrowthExperiments\Specials;

use FormSpecialPage;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use HTMLForm;
use SpecialPage;
use Status;

class SpecialEnrollAsMentor extends FormSpecialPage {

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var IMentorWriter */
	private $mentorWriter;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter
	) {
		parent::__construct( 'EnrollAsMentor', 'enrollasmentor', false );

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
	public function getDescription() {
		return $this->msg( 'growthexperiments-mentorship-enrollasmentor-title' )->text();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();

		if ( $this->mentorProvider->isMentor( $this->getUser() ) ) {
			$this->getOutput()->redirect(
				SpecialPage::getTitleFor( 'MentorDashboard' )->getLocalURL()
			);
		}
		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	protected function preHtml() {
		return $this->msg( 'growthexperiments-mentorship-enrollasmentor-pretext' )->parse();
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitText(
			$this->msg( 'growthexperiments-mentorship-enrollasmentor-enroll' )->text()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		return [
			'message' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-mentorship-enrollasmentor-form-message',
				'help' => $this->msg( 'growthexperiments-mentorship-enrollasmentor-form-message-help' )->text(),
			],
			'weight' => [
				'type' => 'hidden',
				'default' => 2,
			],
			'automaticallyAssigned' => [
				'type' => 'hidden',
				'default' => true,
			],
			'reason' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-mentorship-enrollasmentor-form-reason',
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $this->getUser() );
		$mentor->setIntroText( $data['message'] !== '' ? $data['message'] : null );
		$mentor->setWeight( (int)$data['weight'] );
		$mentor->setAutoAssigned( (bool)$data['automaticallyAssigned'] );

		return Status::wrap( $this->mentorWriter->addMentor(
			$mentor,
			$this->getUser(),
			$data['reason']
		) );
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-mentorship-enrollasmentor-success' );
	}
}
