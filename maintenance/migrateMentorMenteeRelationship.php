<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\PreferenceMentorStore;
use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Migrate primary mentees from PreferenceMentorStore to DatabaseMentorStore
 *
 * PreferenceMentorStore cannot handle other mentors than primary, so this
 * script transfers only primary mentors.
 */
class MigrateMentorMenteeRelationship extends LoggedUpdateMaintenance {
	/** @var PreferenceMentorStore */
	private $preferenceMentorStore;

	/** @var DatabaseMentorStore */
	private $databaseMentorStore;

	/** @var UserFactory */
	private $userFactory;

	/** @var ILoadBalancer */
	private $growthLoadBalancer;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addOption( 'dry-run', 'Do not actually update anything' );
	}

	private function initServices() {
		$services = MediaWikiServices::getInstance();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->preferenceMentorStore = $geServices->getPreferenceMentorStore();
		$this->databaseMentorStore = $geServices->getDatabaseMentorStore();
		$this->userFactory = $services->getUserFactory();
		$this->growthLoadBalancer = $geServices->getLoadBalancer();
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$this->initServices();
		$dryRun = $this->hasOption( 'dry-run' );
		$batchSize = $this->getBatchSize();

		$dbw = $this->getDB( DB_MASTER );
		$growthDbw = $this->growthLoadBalancer->getConnection( DB_MASTER );

		$allRowsCount = (int)$dbw->selectField(
			'user',
			'MAX(user_id)',
			[],
			__METHOD__
		);

		$prevId = 1;
		$curId = $batchSize;
		$updated = 0;

		do {
			$this->output( "... processing users from $prevId to $curId\n" );

			// Start transaction if dry mode is disabled
			$lockingOptions = [];
			if ( !$dryRun ) {
				$dbw->begin( __METHOD__ );

				// Explicitly start transaction on $growthDbw only if it is
				// in a separate database cluster
				if ( $this->getConfig()->get( 'GEDatabaseCluster' ) !== false ) {
					$growthDbw->begin( __METHOD__ );
				}

				$lockingOptions[] = 'LOCK IN SHARE MODE';
			}

			$updateIDs = $dbw->selectFieldValues(
				'user_properties',
				'up_user',
				[
					"up_user >= $prevId",
					"up_user <= $curId",
					'up_property' => PreferenceMentorStore::MENTOR_PREF,
				],
				__METHOD__,
				$lockingOptions
			);

			$updatedIDs = $growthDbw->selectFieldValues(
				'growthexperiments_mentor_mentee',
				'gemm_mentee_id',
				[
					"gemm_mentee_id >= $prevId",
					"gemm_mentee_id <= $prevId"
				],
				__METHOD__
			);

			$updatedRows = 0;
			foreach ( $updateIDs as $updateID ) {
				if ( in_array( $updateID, $updatedIDs ) ) {
					// Already updated, no need to process this one
					continue;
				}

				$mentee = $this->userFactory
					->newFromId( $updateID );
				$mentee->load( User::READ_LATEST );
				$mentor = $this->preferenceMentorStore
					->loadMentorUser(
						$mentee,
						PreferenceMentorStore::ROLE_PRIMARY,
						PreferenceMentorStore::READ_LATEST );
				if ( $mentor === null ) {
					continue;
				}

				// Set the mentor if dry run is not enabled
				if ( !$dryRun ) {
					$this->databaseMentorStore->setMentorForUser(
						$mentee,
						$mentor,
						DatabaseMentorStore::ROLE_PRIMARY
					);
				}
				$updatedRows++;
			}

			if ( !$dryRun ) {
				$this->output( "Updated $updatedRows rows...\n" );
			} else {
				$this->output( "Would update $updatedRows rows...\n" );
			}

			$prevId = $curId + 1;
			$curId += $batchSize;
			$updated += $updatedRows;

			if ( !$dryRun ) {
				$dbw->commit( __METHOD__ );

				// Explicitly commit on $growthDbw only if it is in a separate database cluster
				if ( $this->getConfig()->get( 'GEDatabaseCluster' ) !== false ) {
					$growthDbw->commit( __METHOD__ );
				}

				$this->waitForReplication();
			}
		} while ( $prevId <= $allRowsCount );

		$this->output( "Script finished\n---------------------\n" );
		if ( !$dryRun ) {
			$this->output( "Updated $updated rows\n" );
		} else {
			$this->output( "Would update $updated rows\n" );
		}

		return !$dryRun;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'GrowthExperimentsMigrateMentorMenteeRelationship';
	}
}

$maintClass = MigrateMentorMenteeRelationship::class;
require_once RUN_MAINTENANCE_IF_MAIN;
