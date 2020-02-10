<?php

namespace GrowthExperiments\Specials;

use Config;
use FormSpecialPage;
use GrowthExperiments\ChangeMentor;
use GrowthExperiments\Mentor;
use GrowthExperiments\WikiConfigException;
use LogEventsList;
use LogPager;
use MediaWiki\Logger\LoggerFactory;
use Message;
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
		$this->addHelpLink( 'Help:Growth/Tools/How to claim a mentee' );
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
				str_replace( '_', ' ', $this->config->get( 'GEHomepageMentorsList' ) )
			]
			:
			[ 'growthexperiments-homepage-claimmentee-must-be-mentor',
			  $this->getUser(),
			  str_replace( '_', ' ', $this->config->get( 'GEHomepageMentorsList' ) )
			];
		throw new PermissionsError( null, [ $error ] );
	}

	/**
	 * Get an HTMLForm descriptor array
	 * @return array
	 */
	protected function getFormFields() {
		$req = $this->getRequest();
		$fields = [
			'mentee' => [
				'label-message' => 'growthexperiments-homepage-claimmentee-mentee',
				'type'          => 'user',
				'required'      => true,
			],
			'reason' => [
				'label-message' => 'growthexperiments-homepage-claimmentee-reason',
				'type'          => 'text',
			],
			'stage' => [ 'type' => 'hidden', 'default' => 2 ]
		];
		$stage = $req->getInt( 'wpstage', 1 );
		$this->setMentee( $req->getVal( 'wpmentee' ) );
		if ( $stage >= 2 && $this->menteeIsValid() ) {
			$fields['stage']['default'] = 3;
			$fields['confirm'] = [
				'label-message' => 'growthexperiments-claimmentee-confirm',
				'type' => 'check',
				'default' => false,
			];
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
		$this->setMentee( $data['mentee'] );
		if ( !$this->menteeIsValid() ) {
			return Status::newFatal( 'growthexperiments-homepage-claimmentee-invalid-username' );
		}
		$this->newMentor = $this->getUser();

		$changementor = new ChangeMentor(
			$this->mentee,
			$this->newMentor,
			$this->getContext(),
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			Mentor::newFromMentee( $this->mentee ),
			new LogPager(
				new LogEventsList( $this->getContext() ),
				[ 'growthexperiments' ],
				'',
				$this->mentee->getUserPage()
			)
		);
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
			$this->mentee->getName(),
			$this->getUser()->getName(),
			$this->newMentor->getName(),
			Message::rawParam( $this->getLinkRenderer()->makeLink(
				$this->mentee->getUserPage(), $this->mentee->getName() ) ),
			Message::rawParam( $this->getLinkRenderer()->makeLink(
				$this->newMentor->getUserPage(), $this->newMentor->getName() ) )
		);
	}

	private function setMentee( $name = '' ) {
		$this->mentee = User::newFromName( $name );
	}

	private function menteeIsValid() {
		return $this->mentee instanceof User && $this->mentee->getId();
	}
}
