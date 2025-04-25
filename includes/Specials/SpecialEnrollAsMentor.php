<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;

class SpecialEnrollAsMentor extends FormSpecialPage {

	private Config $wikiConfig;
	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;

	/**
	 * @param Config $wikiConfig
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 */
	public function __construct(
		Config $wikiConfig,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter
	) {
		parent::__construct( 'EnrollAsMentor', 'enrollasmentor', false );

		$this->wikiConfig = $wikiConfig;
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
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
	public function getDescription() {
		return $this->msg( 'growthexperiments-mentorship-enrollasmentor-title' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireNamedUser();

		if ( $this->mentorProvider->isMentor( $this->getUser() ) ) {
			$this->getOutput()->redirect(
				SpecialPage::getTitleFor( 'MentorDashboard' )->getLocalURL()
			);
		}

		if ( $this->mentorWriter->isBlocked( $this->getUser() ) ) {
			$block = $this->getUser()->getBlock();
			if ( !$block ) {
				throw new LogicException(
					'IMentorWriter::isBlocked returns true, but User::getBlock returns null'
				);
			}
			throw new UserBlockedError( $block );
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
				'help-message' => 'growthexperiments-mentorship-enrollasmentor-form-message-help',
				'maxlength' => MentorProvider::INTRO_TEXT_LENGTH,
			],
			'weight' => [
				'type' => 'hidden',
				'default' => IMentorWeights::WEIGHT_NORMAL,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $this->getUser() );
		$mentor->setIntroText( $data['message'] !== '' ? $data['message'] : null );
		$mentor->setWeight( (int)$data['weight'] );

		return Status::wrap( $this->mentorWriter->addMentor(
			$mentor,
			$this->getUser(),
			''
		) );
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-mentorship-enrollasmentor-success' );
	}

	protected function displayRestrictionError() {
		if ( !$this->wikiConfig->get( 'GEMentorshipAutomaticEligibility' ) ) {
			parent::displayRestrictionError();
		}

		throw new PermissionsError( $this->mRestriction, [ [
			'growthexperiments-mentorship-enrollasmentor-error-not-autoeligible',
			$this->wikiConfig->get( 'GEMentorshipMinimumEditcount' ),
			$this->wikiConfig->get( 'GEMentorshipMinimumAge' ),
		] ] );
	}
}
