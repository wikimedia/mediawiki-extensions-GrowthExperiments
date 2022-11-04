<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\ILoadBalancer;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateIsActiveFlagForMentees extends Maintenance {

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var ILoadBalancer */
	private $growthLoadBalancer;

	/** @var MentorStore */
	private $mentorStore;

	/** @var MentorProvider */
	private $mentorProvider;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription(
			'Set gemm_mentee_is_active to false for users who are inactive for longer' .
			'than MentorStore::INACTIVITY_THRESHOLD.'
		);
		$this->addOption( 'force', 'Do the update even if GEMentorshipUseIsActiveFlag is false' );
	}

	/**
	 * Init MediaWiki services
	 */
	private function initServices(): void {
		$services = MediaWikiServices::getInstance();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->userEditTracker = $services->getUserEditTracker();
		$this->growthLoadBalancer = $geServices->getLoadBalancer();
		$this->mentorStore = $geServices->getMentorStore();
		$this->mentorProvider = $geServices->getMentorProvider();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if (
			!$this->getConfig()->get( 'GEMentorshipUseIsActiveFlag' ) &&
			!$this->hasOption( 'force' )
		) {
			$this->output( "The mentee is active flag is disabled. Use --force to bypass.\n" );
			return;
		}

		$this->initServices();

		try {
			$mentors = $this->mentorProvider->getMentors();
		} catch ( WikiConfigException $e ) {
			$this->fatalError( 'List of mentors cannot be fetched.' );
		}

		foreach ( $mentors as $mentorName ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByName( $mentorName );
			if ( !$mentorUser ) {
				$this->output( "Skipping $mentorName, user identity not found." );
				continue;
			}
			$mentees = $this->mentorStore->getMenteesByMentor(
				$mentorUser,
				MentorStore::ROLE_PRIMARY,
				false,
				false
			);

			foreach ( $mentees as $mentee ) {
				$timeDelta = (int)wfTimestamp() - (int)wfTimestamp(
					TS_UNIX,
					$this->userEditTracker->getLatestEditTimestamp( $mentee )
				);
				if ( $timeDelta > MentorStore::INACTIVITY_THRESHOLD ) {
					$this->mentorStore->markMenteeAsInactive( $mentee );
				}
			}
		}
	}
}

$maintClass = UpdateIsActiveFlagForMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
