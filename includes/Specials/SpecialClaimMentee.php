<?php

namespace GrowthExperiments\Specials;

use Config;
use FormSpecialPage;
use GrowthExperiments\ChangeMentor;
use GrowthExperiments\Mentor;
use GrowthExperiments\WikiConfigException;
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

	/**
	 * @var string[]|null List of mentors that can be assigned.
	 */
	private $mentorsList;
	/**
	 * @var Config
	 */
	private $config;

	/**
	 * SpecialClaimMentee constructor.
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		parent::__construct( 'ClaimMentee' );
		$this->config = $config;
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
		try {
			$this->mentorsList = Mentor::getMentors();
		} catch ( WikiConfigException $wikiConfigException ) {
			return false;
		}
		return in_array( $user->getTitleKey(), $this->mentorsList );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError() {
		$error = !$this->mentorsList ?
			[
				'growthexperiments-homepage-mentors-list-missing-or-misconfigured',
				$this->config->get( 'GEHomepageMentorsList' )
			]
			:
			[ 'growthexperiments-homepage-claimmentee-must-be-mentor',
			  $this->getUser(),
			  $this->config->get( 'GEHomepageMentorsList' )
			];
		throw new PermissionsError( null, [ $error ] );
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
