<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\ILoadBalancer;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class UpdateIsActiveFlagForMentees extends Maintenance {

	private UserIdentityLookup $userIdentityLookup;
	private UserEditTracker $userEditTracker;
	private UserFactory $userFactory;
	private ILoadBalancer $growthLoadBalancer;
	private MentorStore $mentorStore;

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 200 );
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription(
			'Set gemm_mentee_is_active to false for users who are inactive for longer' .
			'than $wgRCMaxAge.'
		);
	}

	/**
	 * Init MediaWiki services
	 */
	private function initServices(): void {
		$services = $this->getServiceContainer();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->userFactory = $services->getUserFactory();
		$this->userEditTracker = $services->getUserEditTracker();
		$this->growthLoadBalancer = $geServices->getLoadBalancer();
		$this->mentorStore = $geServices->getMentorStore();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
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
				$this->output(
					"Deleting mentor/mentee relationship for $menteeId, user identity not found.\n"
				);
				$this->mentorStore->dropMenteeRelationship(
					// user does not exist; MentorStore only makes use of the user ID,
					// so construct UserIdentity manually for easier deletion.
					new UserIdentityValue( $menteeId, 'Mentee' )
				);
				continue;
			}

			$lastActivityTimestamp = $this->userEditTracker->getLatestEditTimestamp( $menteeUser );
			if ( $lastActivityTimestamp === false ) {
				$lastActivityTimestamp = $this->userFactory->newFromUserIdentity( $menteeUser )
					->getRegistration();
			}

			$timeDelta = (int)wfTimestamp() - (int)wfTimestamp(
				TS_UNIX,
				$lastActivityTimestamp
			);

			if (
				$lastActivityTimestamp === null ||
				$timeDelta > (int)$this->getConfig()->get( 'RCMaxAge' )
			) {
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

// @codeCoverageIgnoreStart
$maintClass = UpdateIsActiveFlagForMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
