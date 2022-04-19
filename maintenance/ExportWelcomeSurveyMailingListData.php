<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\HomepageHooks;
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
 */
class ExportWelcomeSurveyMailingListData extends Maintenance {

	/** @var string */
	private $outputFormat = 'text';
	/** @var resource */
	private $handle;

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
		$homepageEnabledDefaultValue = $services->getUserOptionsLookup()
			->getDefaultOption( HomepageHooks::HOMEPAGE_PREF_ENABLE );

		if ( $from === null || $to === null ) {
			$this->fatalError( "--from and --to have to be provided and be valid timestamps" . PHP_EOL );
		}

		$this->outputFormat = $this->getOption( 'output-format', 'text' );
		if ( !in_array( $this->outputFormat, [ 'text', 'csv' ] ) ) {
			$this->fatalError( "--output-format must be one of 'text' or 'csv'" );
		}
		$queryBuilderTemplate = new SelectQueryBuilder( $dbr );
		$queryBuilderTemplate
			->table( 'user' )
			->join( 'user_properties', 'survey_prop', [
				'user_id = survey_prop.up_user',
				'survey_prop.up_property' => WelcomeSurvey::SURVEY_PROP,
			] )
			->leftJoin( 'user_properties', 'homepage_prop', [
				'user_id = homepage_prop.up_user',
				'homepage_prop.up_property' => HomepageHooks::HOMEPAGE_PREF_ENABLE,
			] )
			->fields( [
				'user_id',
				'user_email',
				'user_email_authenticated',
				'survey_data' => 'survey_prop.up_value',
				'homepage_enabled' => 'homepage_prop.up_value',
			] )
			// need to order by ID so we can use ID ranges for query continuation
			->orderBy( 'user_id', SelectQueryBuilder::SORT_ASC )
			->limit( $this->getBatchSize() )
			->caller( __METHOD__ );

		// We don't have timestamps associated with welcome survey responses in a way that's easy to query.
		// So we'll iterate over all welcome survey submissions and look for ones that 1) are submitted 2) have
		// the mailinglist option set to something truthy.
		$fromUserId = 0;
		$this->handle = fopen( 'php://output', 'w' );
		$headers = [ 'Email Address', 'Opt-in date', 'Group', 'User ID', 'Is email address confirmed' ];
		$this->writeToHandle( $headers );
		do {
			$queryBuilder = clone $queryBuilderTemplate;
			$queryBuilder->andWhere( "user_id > $fromUserId" );
			$result = $queryBuilder->fetchResultSet();
			foreach ( $result as $row ) {
				$fromUserId = (int)$row->user_id;
				$homepageEnabled = (bool)( $row->homepage_enabled ?? $homepageEnabledDefaultValue );
				$welcomeSurveyResponse = FormatJson::decode( (string)$row->survey_data, true );
				// We only want to export survey responses in the T303240_mailinglist group,
				// see https://gerrit.wikimedia.org/r/c/operations/mediawiki-config/+/775951
				// and only when the user gets the Growth features
				if ( !$homepageEnabled
					|| !$welcomeSurveyResponse
					|| !isset( $welcomeSurveyResponse['_submit_date'] )
					|| $welcomeSurveyResponse['_group'] !== 'T303240_mailinglist'
				) {
					continue;
				}
				$welcomeSurveyResponseSubmitDate = wfTimestampOrNull( TS_MW, $welcomeSurveyResponse['_submit_date' ] );
				if ( $welcomeSurveyResponseSubmitDate > $to || $welcomeSurveyResponseSubmitDate < $from ) {
					continue;
				}
				if ( isset( $welcomeSurveyResponse['mailinglist'] ) && $welcomeSurveyResponse['mailinglist'] ) {
					// user_email_authenticated is the timestamp of when the email was confirmed; we want a 1 or 0
					// to indicate if the email is confirmed.
					$outputData = [
						$row->user_email,
						$welcomeSurveyResponseSubmitDate,
						$welcomeSurveyResponse['_group'],
						$row->user_id,
						$row->user_email_authenticated ? 1 : 0
					];
				} else {
					continue;
				}
				$this->writeToHandle( $outputData );

			}
		} while ( $result->numRows() );
	}

	/**
	 * @param array $data
	 * @return void
	 */
	private function writeToHandle( array $data ): void {
		if ( $this->outputFormat === 'text' ) {
			fputs( $this->handle, implode( "\t", $data ) . PHP_EOL );
		} else {
			fputcsv( $this->handle, $data );
		}
	}
}

$maintClass = ExportWelcomeSurveyMailingListData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
