<?php

namespace GrowthExperiments\Specials;

use FormSpecialPage;
use GrowthExperiments\ChangeMentor;
use GrowthExperiments\Mentor;
use MediaWiki\Logger\LoggerFactory;
use PermissionsError;
use Status;
use User;

class SpecialClaimMentee extends FormSpecialPage {
	/**
	 * @var User|null
	 */
	private $mentee;
	/**
	 * @var User|null
	 */
	private $newMentor;

	public function __construct() {
		parent::__construct( 'ClaimMentee' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-homepage-claimmentee-title' )->text();
	}

	protected function preText() {
		return $this->msg( 'growthexperiments-homepage-claimmentee-pretext' )->params(
			$this->getUser()->getName()
		)->escaped();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();
		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		return in_array( $user->getTitleKey(), Mentor::getMentors() );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError() {
		throw new PermissionsError( null, [
			[
				'growthexperiments-homepage-claimmentee-must-be-mentor',
				$this->getUser()
				]
		] );
	}

	/**
	 * Get an HTMLForm descriptor array
	 * @return array
	 */
	protected function getFormFields() {
		$fields = [
			'mentee' => [
				'label-message' => 'growthexperiments-homepage-claimmentee-mentee',
				'type'          => 'text',
				'required'      => true,
			],
			'reason' => [
				'label-message' => 'growthexperiments-homepage-claimmentee-reason',
				'type'          => 'text',
			],
			'stage' => [ 'type' => 'hidden', 'default' => 2 ]
		];
		$req = $this->getRequest();
		$stage = $req->getInt( 'wpstage', 1 );
		if ( $stage >= 2 ) {
			$fields['confirm'] = [
				'label-message' => 'growthexperiments-claimmentee-confirm',
				'type' => 'check',
				'default' => false,
			];
			$fields['stage']['default'] = 3;
		}
		return $fields;
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->mentee = User::newFromName( $data['mentee'] );
		if ( $this->mentee === false ) {
			return Status::newFatal( 'growthexperiments-homepage-claimmentee-invalid-username' );
		}
		$this->newMentor = $this->getUser();

		$logger = LoggerFactory::getInstance( 'GrowthExperiments' );
		$changementor = new ChangeMentor( $this->mentee, $this->newMentor, $this->getContext(), $logger );
		if (
			$data['confirm'] !== true
			&& $data['stage'] !== 3
			&& $changementor->wasMentorChanged()
		) {
			return Status::newFatal(
				'growthexperiments-homepage-claimmentee-alreadychanged',
				$this->mentee,
				$this->newMentor
			);
		}

		return $changementor->execute( $this->newMentor, $data['reason'] );
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg(
			'growthexperiments-homepage-claimmentee-success',
			$this->mentee,
			$this->newMentor
		);
	}
}
