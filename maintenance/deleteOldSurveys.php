<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\WelcomeSurvey;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\User;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\Rdbms\IDBAccessObject;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Delete welcome surveys older than a cutoff date.
 */
class DeleteOldSurveys extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'Delete welcome survey data older than a year.' );
		$this->addOption( 'cutoff', 'Cutoff interval (data older than this many days will be deleted)',
			true, true );
		$this->addOption( 'dry-run', 'Simulate execution without writing any changes' );
		$this->addOption( 'verbose', 'Verbose output', false, false, 'v' );
		$this->setBatchSize( 1000 );
	}

	/** @inheritDoc */
	public function execute() {
		$cutoffDays = (int)$this->getOption( 'cutoff' );
		$dryRun = $this->hasOption( 'dry-run' );
		$verbose = $this->hasOption( 'verbose' );

		if ( !$cutoffDays ) {
			$this->fatalError( 'Invalid cutoff period: ' . $this->getOption( 'cutoff' ) );
		}

		// This seems to be the least ugly way of using a relative date specifier while
		// keeping MWTimestamp::setFakeTime working.
		$ts = MWTimestamp::getInstance();
		$ts->timestamp->modify( "-$cutoffDays day" );
		$cutoffDate = $ts->getTimestamp( TS_MW );
		$this->output( "Deleting data before $cutoffDate (over $cutoffDays days old)" .
			( $dryRun ? ' (dry run)' : '' ) . "\n" );

		$dbr = $this->getReplicaDB();
		$dbw = $this->getPrimaryDB();
		$fromUserId = 0;
		$break = false;
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		do {
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'user_id', 'up_value', 'user_registration' ] )
				->from( 'user_properties' )
				->join( 'user', null, [ 'user_id = up_user' ] )
				->where( [
					'up_property' => WelcomeSurvey::SURVEY_PROP,
					$dbr->expr( 'user_id', '>', $fromUserId ),
				] )
				->orderBy( 'user_id ASC' )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ )->fetchResultSet();
			$ids = [];
			$deletedCount = $skippedCount = 0;
			foreach ( $res as $row ) {
				$fromUserId = $row->user_id;
				$userRegistration = wfTimestampOrNull( TS_MW, $row->user_registration );
				$welcomeSurveyData = json_decode( $row->up_value, true );
				if ( $userRegistration > $cutoffDate ) {
					// The submit date cannot be smaller than the registration date, and registration
					// date is monotonic; we can stop here.
					if ( $verbose ) {
						$this->output( "  Stopping at user:$row->user_id which has past-cutoff registration date " .
							$userRegistration . "\n" );
					}
					$break = true;
					break;
				} elseif ( isset( $welcomeSurveyData['_submit_date'] ) &&
					$welcomeSurveyData['_submit_date'] > $cutoffDate
				) {
					// The submit date is not monotonic by user id; we can skip this record but need to
					// check later ones.
					if ( $verbose ) {
						$this->output( "  Skipping user:$row->user_id, past-cutoff survey submit date " .
							$welcomeSurveyData['_submit_date'] . "\n" );
					}
					$skippedCount++;
				} else {
					if ( $verbose ) {
						$this->output( "  Deleting survey data for user:$row->user_id\n" );
					}
					$ids[] = $row->user_id;
				}
			}
			foreach ( $ids as $id ) {
				if ( !$dryRun ) {
					$this->beginTransaction( $dbw, __METHOD__ );
					$user = User::newFromId( $id );
					$user->load( IDBAccessObject::READ_EXCLUSIVE );
					// Setting an option to null will assign it the default value, which in turn
					// will delete it (meaning we won't have to reprocess this row on the next run).
					$userOptionsManager->setOption( $user, WelcomeSurvey::SURVEY_PROP, null );
					$user->saveSettings();
					$this->commitTransaction( $dbw, __METHOD__ );
				}
				$deletedCount++;
			}
			$this->output( "Processed users up to ID $fromUserId\n" );
		} while ( !$break && $res->numRows() === $this->mBatchSize );
		$this->output( "Deleted: $deletedCount, skipped: $skippedCount\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = DeleteOldSurveys::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
