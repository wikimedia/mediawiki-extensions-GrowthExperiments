<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\WelcomeSurvey;
use Maintenance;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\SelectQueryBuilder;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

/**
 * One-off script to export data from the welcome survey for users who opt-in to mailing list.
 *
 */
class ExportWelcomeSurveyMailingListData extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'from',
			'Export data starting from this date, e.g. 20220301000000', false, true );
		$this->addOption( 'to',
			'Export date up to this date, e.g. 20220316000000', false, true );
		$this->addOption(
			'output-format',
			'Output format for the results, "text" or "csv"',
			false
		);
	}

	public function execute() {
		$services = MediaWikiServices::getInstance();
		$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$from = wfTimestampOrNull( TS_MW, $this->getOption( 'from' ) );
		$to = wfTimestampOrNull( TS_MW, $this->getOption( 'to' ) );

		if ( $from === null || $to === null ) {
			$this->fatalError( "--from and --to have to be provided and be valid timestamps" . PHP_EOL );
		}

		$outputFormat = $this->getOption( 'output-format', 'text' );
		if ( !in_array( $outputFormat, [ 'text', 'csv' ] ) ) {
			$this->fatalError( "--output-format must be one of 'text' or 'csv'" );
		}
		$queryBuilderTemplate = new SelectQueryBuilder( $dbr );
		$queryBuilderTemplate
			->table( 'user_properties' )
			->join( 'user', null, [
				'user_id = up_user',
			] )
			->fields( [ 'user_id', 'user_email', 'user_email_authenticated', 'up_property', 'up_value' ] )
			->where( [ 'up_property' => WelcomeSurvey::SURVEY_PROP ] )
			// need to order by ID so we can use ID ranges for query continuation
			->orderBy( 'user_id', SelectQueryBuilder::SORT_ASC )
			->limit( $this->getBatchSize() )
			->caller( __METHOD__ );

		// We don't have timestamps associated with welcome survey responses in a way that's easy to query.
		// So we'll iterate over all welcome survey submissions and look for ones that 1) are submitted 2) have
		// the mailinglist option set to something truthy.
		$fromUserId = 0;
		$handle = fopen( 'php://output', 'w' );
		do {
			$queryBuilder = clone $queryBuilderTemplate;
			$queryBuilder->andWhere( "user_id > $fromUserId" );
			$result = $queryBuilder->fetchResultSet();
			foreach ( $result as $row ) {
				$fromUserId = (int)$row->user_id;
				$welcomeSurveyResponse = FormatJson::decode( (string)$row->up_value, true );
				if ( !$welcomeSurveyResponse || !isset( $welcomeSurveyResponse['_submit_date'] ) ) {
					continue;
				}
				$welcomeSurveyResponseSubmitDate = wfTimestampOrNull( TS_MW, $welcomeSurveyResponse['_submit_date' ] );
				if ( $welcomeSurveyResponseSubmitDate > $to || $welcomeSurveyResponseSubmitDate < $from ) {
					continue;
				}
				if ( isset( $welcomeSurveyResponse['mailinglist'] ) && $welcomeSurveyResponse['mailinglist'] ) {
					// user_email_authenticated is the timestamp of when the email was confirmed; we want a 1 or 0
					// to indicate if the email is confirmed.
					$outputData = [ $row->user_id, $row->user_email, $row->user_email_authenticated ? 1 : 0 ];
				} else {
					continue;
				}
				if ( $outputFormat === 'text' ) {
					fputs( $handle, implode( "\t", $outputData ) . PHP_EOL );
				} else {
					fputcsv( $handle, $outputData );
				}
			}
		} while ( $result->numRows() );
	}
}

$maintClass = ExportWelcomeSurveyMailingListData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
