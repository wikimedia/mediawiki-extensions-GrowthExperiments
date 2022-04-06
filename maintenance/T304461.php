<?php

namespace GrowthExperiments\Maintenance;

use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to do the T304461 maintenance
 *
 * This will remove mentor/mentee relationship from user_properties (ie. rows with
 * up_property='growthexperiments-mentor-id').
 */
class T304461 extends LoggedUpdateMaintenance {

	/** @var string */
	private const PROPERTY_NAME = 'growthexperiments-mentor-id';

	/** @var IDatabase */
	private $dbr;

	/** @var IDatabase */
	private $dbw;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Remove mentor/mentee relationship from user_properties' );

		$this->addOption( 'delete', 'Actually do the deletion.' );

		$this->setBatchSize( 100 );
	}

	private function initServices(): void {
		$services = MediaWikiServices::getInstance();

		$this->dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$this->dbw = $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$this->initServices();
		$dryRun = !$this->hasOption( 'delete' );

		$rowsNum = 0;
		$rowsInThisBatch = -1;
		$maxUserId = 0;
		while ( $rowsInThisBatch != 0 ) {
			$minUserId = $this->dbr->selectField(
				'user_properties',
				'MIN(up_user)',
				[
					'up_property' => self::PROPERTY_NAME,
					"up_user > $maxUserId",
				],
				__METHOD__
			);
			if ( $minUserId === null ) {
				// no rows left
				break;
			}

			// BETWEEN is not inclusive, add one more
			$maxUserId = $minUserId + $this->getBatchSize() + 1;

			$conds = [
				'up_property' => self::PROPERTY_NAME,
				'up_user BETWEEN ' . $this->dbw->addQuotes( $minUserId ) . ' AND '
				. $this->dbw->addQuotes( $maxUserId ),
			];

			if ( !$dryRun ) {
				$this->dbw->delete(
					'user_properties',
					$conds,
					__METHOD__
				);
				$rowsInThisBatch = $this->dbw->affectedRows();
			} else {
				$rowsInThisBatch = $this->dbr->selectField(
					'user_properties',
					'COUNT(*)',
					$conds,
					__METHOD__
				);
			}

			$this->waitForReplication();
			$rowsNum += $rowsInThisBatch;
		}

		if ( !$dryRun ) {
			$this->output( "Done! Deleted $rowsNum rows.\n" );
		} else {
			$this->output( "Would delete $rowsNum rows.\n" );
		}

		return !$dryRun;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'GrowthExperimentsT304461';
	}
}

$maintClass = T304461::class;
require_once RUN_MAINTENANCE_IF_MAIN;
