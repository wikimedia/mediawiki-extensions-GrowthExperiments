<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
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

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 200 );
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription(
			'Set gemm_mentee_is_active to false for users who are inactive for longer' .
			'than $wgRCMaxAge.'
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

		$dbr = $this->growthLoadBalancer->getConnection( DB_REPLICA );
		$menteeIds = $dbr->newSelectQueryBuilder()
			->select( 'gemm_mentee_id' )
			->from( 'growthexperiments_mentor_mentee' )
			->where( [
				'gemm_mentor_role' => MentorStore::ROLE_PRIMARY,
				'gemm_mentee_is_active' => true,
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$thisBatch = 0;
		foreach ( $menteeIds as $menteeId ) {
			$menteeUser = $this->userIdentityLookup->getUserIdentityByUserId( $menteeId );
			if ( !$menteeUser ) {
				$this->output( "Skipping user ID $menteeId, user identity not found.\n" );
				continue;
			}

			$timeDelta = (int)wfTimestamp() - (int)wfTimestamp(
				TS_UNIX,
				$this->userEditTracker->getLatestEditTimestamp( $menteeUser )
			);
			if ( $timeDelta > (int)$this->getConfig()->get( 'RCMaxAge' ) ) {
				$this->mentorStore->markMenteeAsInactive( $menteeUser );
				$thisBatch++;

				if ( $thisBatch >= $this->getBatchSize() ) {
					$this->waitForReplication();
					$thisBatch = 0;
				}
			}
		}
	}
}

$maintClass = UpdateIsActiveFlagForMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
