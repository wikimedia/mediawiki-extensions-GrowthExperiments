<?php

namespace GrowthExperiments\Maintenance;

use Generator;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Purge expired rows related to mentor status from user_properties
 */
class PurgeExpiredMentorStatus extends Maintenance {

	/** @var IReadableDatabase */
	private $dbr;

	/** @var IDatabase */
	private $dbw;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription(
			'Remove expired values of MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF from user_properties'
		);
		$this->addOption( 'dry-run', 'Do not actually change anything.' );
		$this->setBatchSize( 100 );
	}

	private function initServices(): void {
		$this->dbr = $this->getReplicaDB();
		$this->dbw = $this->getPrimaryDB();
	}

	private function getRows(): Generator {
		yield from $this->dbr->newSelectQueryBuilder()
			->select( [ 'up_user', 'up_value' ] )
			->from( 'user_properties' )
			->where( [ 'up_property' => MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF ] )
			->caller( __METHOD__ )->fetchResultSet();
	}

	private function filterAndBatch(): Generator {
		$batch = [];
		foreach ( $this->getRows() as $row ) {
			if (
				$row->up_value === null ||
				ConvertibleTimestamp::convert( TS_UNIX, $row->up_value ) < wfTimestamp( TS_UNIX )
			) {
				$batch[] = $row->up_user;

				if ( count( $batch ) >= $this->getBatchSize() ) {
					yield $batch;
					$batch = [];
				}
			}
		}

		if ( $batch !== [] ) {
			yield $batch;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		$deletedCount = 0;
		foreach ( $this->filterAndBatch() as $batch ) {
			$this->deleteTimestamps( $batch );
			$deletedCount += count( $batch );
		}

		if ( $this->getOption( 'dry-run' ) ) {
			$this->output( "Would delete $deletedCount rows from user_properties.\n" );
		} else {
			$this->output( "Deleted $deletedCount rows from user_properties.\n" );
		}
	}

	private function deleteTimestamps( array $toDelete ): void {
		if ( $this->getOption( 'dry-run' ) ) {
			return;
		}
		$this->beginTransaction( $this->dbw, __METHOD__ );
		$this->dbw->newDeleteQueryBuilder()
			->deleteFrom( 'user_properties' )
			->where( [
				'up_property' => MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF,
				'up_user' => $toDelete
			] )
			->caller( __METHOD__ )
			->execute();
		$this->commitTransaction( $this->dbw, __METHOD__ );
	}
}

// @codeCoverageIgnoreStart
$maintClass = PurgeExpiredMentorStatus::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
