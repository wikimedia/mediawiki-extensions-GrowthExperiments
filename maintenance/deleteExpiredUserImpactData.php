<?php

namespace GrowthExperiments\Maintenance;

use DateTime;
use Exception;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class DeleteExpiredUserImpactData extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Delete unused old data from the growthexperiments_user_impact table.' );
		$this->addOption( 'expiry', 'A relative timestring fragment passed to DateTime, such as "30days".',
			false, true );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute() {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$dbw = $growthServices->getLoadBalancer()->getConnection( DB_PRIMARY );

		$expiry = $this->getOption( 'expiry', '30days' );
		$expiryTimestamp = $this->getTimestampFromRelativeDate( $expiry );

		$queryBuilder = $dbw->newSelectQueryBuilder()
			->select( 'geui_user_id' )
			->from( DatabaseUserImpactStore::TABLE_NAME )
			->where( $dbw->expr( 'geui_timestamp', '<', $dbw->timestamp( $expiryTimestamp ) ) )
			->orderBy( 'geui_timestamp', SelectQueryBuilder::SORT_ASC )
			->limit( $this->getBatchSize() )
			->caller( __METHOD__ );

		$deletedCount = 0;
		$idsToDelete = $queryBuilder->fetchFieldValues();
		while ( $idsToDelete !== [] ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( DatabaseUserImpactStore::TABLE_NAME )
				->where( [ 'geui_user_id' => $idsToDelete ] )
				->caller( __METHOD__ )
				->execute();
			$deletedCount += count( $idsToDelete );
			$this->output( '.' );
			$this->waitForReplication();
			$idsToDelete = $queryBuilder->fetchFieldValues();
		}
		$this->output( "\nDeleted $deletedCount rows\n" );
	}

	/**
	 * @param string $relativeDate A relative date string fragment that will be prefixed with a
	 *   minus sign and passed to the DateTime constructor
	 * @return string TS_MW formatted timestamp
	 */
	private function getTimestampFromRelativeDate( string $relativeDate ): string {
		try {
			$timestamp = new ConvertibleTimestamp( new DateTime( 'now - ' . $relativeDate ) );
		} catch ( Exception $e ) {
			$this->fatalError( $e->getMessage() );
		}
		return $timestamp->getTimestamp( TS_MW );
	}

}

// @codeCoverageIgnoreStart
$maintClass = DeleteExpiredUserImpactData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
