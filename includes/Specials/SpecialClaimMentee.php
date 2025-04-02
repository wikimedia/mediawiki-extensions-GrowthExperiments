<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use PermissionsError;

class SpecialClaimMentee extends FormSpecialPage {

	/** @var User[] */
	private array $mentees;

	private ?User $newMentor;
	private MentorProvider $mentorProvider;
	private ChangeMentorFactory $changeMentorFactory;
	private Config $wikiConfig;

	public function __construct(
		MentorProvider $mentorProvider,
		ChangeMentorFactory $changeMentorFactory,
		Config $wikiConfig
	) {
		parent::__construct( 'ClaimMentee' );

		$this->mentorProvider = $mentorProvider;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->wikiConfig = $wikiConfig;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-homepage-claimmentee-title' );
	}

	/** @inheritDoc */
	protected function preHtml() {
		return Html::element(
			'p',
			[],
			$this->msg( 'growthexperiments-homepage-claimmentee-pretext' )->params(
				$this->getUser()->getName()
			)->text()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireNamedUser();
		$this->addHelpLink( 'Help:Growth/Tools/How to claim a mentee' );
		parent::execute( $par );
	}

	/**
	 * @inheritDoc
	 */
	public function isListed() {
		return $this->userCanExecute( $this->getUser() );
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		return $this->mentorProvider->isMentor( $user );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError() {
		$signupTitle = $this->mentorProvider->getSignupTitle();

		if ( $signupTitle === null ) {
			throw new PermissionsError(
				null,
				[ 'growthexperiments-homepage-mentors-list-missing-or-misconfigured-generic' ]
			);
		}

		throw new PermissionsError(
			null,
			[ [ 'growthexperiments-homepage-claimmentee-must-be-mentor',
				$this->getUser()->getName(),
				$signupTitle->getPrefixedText() ] ]
		);
	}

	/**
	 * Get an HTMLForm descriptor array
	 * @return array
	 */
	protected function getFormFields() {
		$req = $this->getRequest();
		$fields = [
			'mentees' => [
				'label-message' => 'growthexperiments-homepage-claimmentee-mentee',
				'type'          => 'usersmultiselect',
				'exists'        => true,
				'required'      => true,
				'excludetemp'   => true,
			],
			'reason' => [
				'label-message' => 'growthexperiments-homepage-claimmentee-reason',
				'type'          => 'text',
			],
			'stage' => [ 'type' => 'hidden', 'default' => 2 ]
		];
		$stage = $req->getInt( 'wpstage', 1 );
		$this->setMentees( $req->getVal( 'wpmentees', '' ) );
		if ( $stage >= 2 && $this->validateMentees() ) {
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
		$this->setMentees( $data['mentees'] );

		// Should be caught by exits => true, but just to be sure
		if ( !$this->validateMentees() ) {
			return Status::newFatal( 'growthexperiments-homepage-claimmentee-invalid-username' );
		}

		$this->newMentor = $this->getUser();

		$status = Status::newGood();
		$logger = LoggerFactory::getInstance( 'GrowthExperiments' );
		foreach ( $this->mentees as $mentee ) {
			$changementor = $this->changeMentorFactory->newChangeMentor(
				$mentee,
				$this->newMentor
			);

			if (
				$data['confirm'] !== true
				&& $data['stage'] !== 3
				&& $changementor->wasMentorChanged()
			) {
				return Status::newFatal(
					'growthexperiments-homepage-claimmentee-alreadychanged',
					$mentee,
					$this->newMentor
				);
			}

			$status->merge( $changementor->execute( $this->newMentor, $data['reason'] ) );
			if ( !$status->isOK() ) {
				// Do not process next users if at least one failed
				return $status;
			}
		}

		return $status;
	}

	public function onSuccess() {
		$mentees = array_map( static function ( UserIdentity $user ) {
			return Linker::userLink( $user->getId(), $user->getName() );
		}, $this->mentees );

		$language = $this->getLanguage();

		$this->getOutput()->addWikiMsg(
			'growthexperiments-homepage-claimmentee-success',
			Message::rawParam( $language->listToText( $mentees ) ),
			$language->formatNum( count( $mentees ) ),
			$this->getUser()->getName(),
			$this->newMentor->getName(),
			Message::rawParam( $this->getLinkRenderer()->makeLink(
				$this->newMentor->getUserPage(), $this->newMentor->getName() ) )
		);
	}

	private function setMentees( string $namesRaw = '' ) {
		$names = explode( "\n", $namesRaw );
		$this->mentees = [];

		foreach ( $names as $name ) {
			$user = User::newFromName( $name );
			if ( $user !== false ) {
				$this->mentees[] = $user;
			}
		}
	}

	private function validateMentees(): bool {
		foreach ( $this->mentees as $mentee ) {
			if ( !( $mentee instanceof User && $mentee->isNamed() ) ) {
				return false;
			}
		}
		return true;
	}
}
