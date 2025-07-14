<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\ReassignMenteesFactory;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Context\RequestContext;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\IReadableDatabase;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class ReassignMentees extends Maintenance {

	private IReadableDatabase $growthDbr;
	private UserIdentityLookup $userIdentityLookup;
	private MentorProvider $mentorProvider;
	private ReassignMenteesFactory $reassignMenteesFactory;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription(
			'Reassign mentees assigned to a particular user (who is not in the mentor list)'
		);
		$this->addOption(
			'performer',
			'Who should the reassignments be attributed to?',
			true,
			true
		);
		$this->addOption(
			'all',
			'Reassign mentees assigned to all non-mentor users'
		);
		$this->addOption(
			'mentor',
			'Username of the mentor to do the reassigning for',
			false,
			true
		);
	}

	private function init() {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$growthLb = $growthServices->getLoadBalancer();

		$this->growthDbr = $growthLb->getConnection( DB_REPLICA );
		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->mentorProvider = $growthServices->getMentorProvider();
		$this->reassignMenteesFactory = $growthServices->getReassignMenteesFactory();
	}

	/**
	 * @return UserIdentity[]
	 */
	private function getAllUnofficialMentors(): array {
		$officialMentorIds = array_map( static function ( UserIdentity $user ) {
			return $user->getId();
		}, $this->mentorProvider->getMentors() );

		$unofficialMentorIds = $this->growthDbr->newSelectQueryBuilder()
			->select( 'gemm_mentor_id' )
			->distinct()
			->from( 'growthexperiments_mentor_mentee' )
			->where( [
				'gemm_mentor_role' => MentorStore::ROLE_PRIMARY,
				$this->growthDbr->expr( 'gemm_mentor_id', '!=', $officialMentorIds ),
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		if ( $unofficialMentorIds === [] ) {
			return [];
		}

		return iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $unofficialMentorIds )
			->caller( __METHOD__ )
			->fetchUserIdentities() );
	}

	/**
	 * @return UserIdentity[]
	 */
	private function getMentorsToProcess(): array {
		$mentors = [];
		if ( !$this->hasOption( 'all' ) && !$this->hasOption( 'mentor' ) ) {
			$this->fatalError( 'ERROR: Either --all or --mentor needs to be provided.' );
		} elseif ( $this->hasOption( 'all' ) && $this->hasOption( 'mentor' ) ) {
			$this->fatalError( 'ERROR: --all and --mentor together do not make sense.' );
		} elseif ( $this->hasOption( 'mentor' ) ) {
			$mentor = $this->userIdentityLookup->getUserIdentityByName( $this->getOption( 'mentor' ) );
			if ( !$mentor ) {
				$this->fatalError( 'ERROR: User not found.' );
			}
			$mentors[] = $mentor;
		} elseif ( $this->hasOption( 'all' ) ) {
			$mentors = $this->getAllUnofficialMentors();
		}
		return $mentors;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->init();

		$performerName = $this->getOption( 'performer' );
		$performer = $this->userIdentityLookup->getUserIdentityByName( $performerName );
		if ( !$performer ) {
			$this->fatalError( 'ERROR: User "' . $performerName . '" does not exist.' );
		}

		$mentors = $this->getMentorsToProcess();
		foreach ( $mentors as $mentor ) {
			if ( $this->mentorProvider->isMentor( $mentor ) ) {
				$this->error(
					"ERROR: User \"{$mentor->getName()}\" is currently enrolled as a mentor. " .
					'Remove them before the reassignment.'
				);
				continue;
			}

			$this->reassignMenteesFactory->newReassignMentees(
				$performer,
				$mentor,
				RequestContext::getMain()
			)->doReassignMentees(
				null,
				'growthexperiments-quit-mentorship-reassign-mentees-log-message',
				$mentor->getName()
			);
		}

		$this->output( 'Done!' . PHP_EOL );
	}
}

// @codeCoverageIgnoreStart
$maintClass = ReassignMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
