<?php

namespace GrowthExperiments\Specials;

use Config;
use FormSpecialPage;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\WikiConfigException;
use Html;
use Linker;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentity;
use Message;
use PermissionsError;
use Status;
use User;

class SpecialClaimMentee extends FormSpecialPage {
	/**
	 * @var User[]
	 */
	private $mentees;

	/**
	 * @var User|null
	 */
	private $newMentor;

	/**
	 * @var string[]|null List of mentors that can be assigned.
	 */
	private $mentorsList;

	/** @var MentorManager */
	private $mentorManager;

	/** @var ChangeMentorFactory */
	private $changeMentorFactory;

	/** @var Config */
	private $wikiConfig;

	/**
	 * @param MentorManager $mentorManager
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param Config $wikiConfig
	 */
	public function __construct(
		MentorManager $mentorManager,
		ChangeMentorFactory $changeMentorFactory,
		Config $wikiConfig
	) {
		parent::__construct( 'ClaimMentee' );
		$this->mentorManager = $mentorManager;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->wikiConfig = $wikiConfig;
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
		$this->requireLogin();
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
		try {
			$this->mentorsList = $this->mentorManager->getMentors();
		} catch ( WikiConfigException $wikiConfigException ) {
			return false;
		}
		return in_array( $user->getName(), $this->mentorsList );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError() {
		if ( $this->mentorsList === null ) {
			throw new PermissionsError(
				null,
				[ 'growthexperiments-homepage-mentors-list-missing-or-misconfigured-generic' ]
			);
		}

		if ( $this->mentorManager instanceof MentorPageMentorManager ) {
			// User is not signed up at a page
			if ( $this->mentorManager->getManuallyAssignedMentorsPage() !== null ) {
				// User is not signed up at either auto-assignment page, or the manual page
				$error = [ 'growthexperiments-homepage-claimmentee-must-be-mentor-two-lists',
					$this->getUser(),
					str_replace(
						'_',
						' ',
						$this->wikiConfig->get( 'GEHomepageMentorsList' )
					),
					str_replace(
						'_',
						' ',
						$this->wikiConfig->get( 'GEHomepageManualAssignmentMentorsList' )
					)
				];
			} else {
				// User is not signed up at the auto assignment page
				$error = [ 'growthexperiments-homepage-claimmentee-must-be-mentor',
					$this->getUser(),
					str_replace(
						'_',
						' ',
						$this->wikiConfig->get( 'GEHomepageMentorsList' )
					)
				];
			}
		} else {
			// User is just not a mentor, display a generic access denied message - no details available
			$error = [ 'growthexperiments-homepage-claimmentee-must-be-mentor-generic', $this->getUser() ];
		}

		throw new PermissionsError( null, [ $error ] );
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
				'required'      => true
			],
			'reason' => [
				'label-message' => 'growthexperiments-homepage-claimmentee-reason',
				'type'          => 'text',
			],
			'stage' => [ 'type' => 'hidden', 'default' => 2 ]
		];
		$stage = $req->getInt( 'wpstage', 1 );
		$this->setMentees( $req->getVal( 'wpmentees' ) );
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
				$this->newMentor,
				$this->getContext()
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

	private function setMentees( $namesRaw = '' ) {
		$names = explode( "\n", $namesRaw );
		$this->mentees = [];

		foreach ( $names as $name ) {
			$user = User::newFromName( $name );
			if ( $user !== false ) {
				$this->mentees[] = $user;
			}
		}
	}

	private function validateMentees() {
		foreach ( $this->mentees as $mentee ) {
			if ( !( $mentee instanceof User && $mentee->isRegistered() ) ) {
				return false;
			}
		}
		return true;
	}
}
