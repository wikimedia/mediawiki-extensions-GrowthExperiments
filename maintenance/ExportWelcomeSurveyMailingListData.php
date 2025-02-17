<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\WelcomeSurvey;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

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
			'Export data starting from this registration timestamp, e.g. 20220301000000', false, true );
		$this->addOption( 'to',
			'Export date up to this registration timestamp, e.g. 20220316000000', false, true );
		$this->addOption(
			'question',
			'Welcome survey checkbox question to be used',
			true,
			true
		);
		$this->addOption(
			'group',
			'Welcome survey group to process (if omitted, all groups are processed)',
			false,
			true
		);
		$this->addOption(
			'output-format',
			'Output format for the results, "text" or "csv"',
			false
		);
		$this->addOption( 'debug', 'Show debug output' );
	}

	public function execute() {
		$dbr = $this->getReplicaDB();

		$from = wfTimestampOrNull( TS_MW, $this->getOption( 'from' ) );
		$to = wfTimestampOrNull( TS_MW, $this->getOption( 'to' ) );
		if ( !$from || !$to ) {
			$this->fatalError( "--from and --to have to be provided and be valid timestamps" . PHP_EOL );
		}
		$this->outputFormat = $this->getOption( 'output-format', 'text' );
		if ( !in_array( $this->outputFormat, [ 'text', 'csv' ] ) ) {
			$this->fatalError( "--output-format must be one of 'text' or 'csv'" );
		}

		$services = $this->getServiceContainer();
		$fromId = $this->getLastUserIdBeforeRegistrationDate( $dbr, $from );
		$toId = $this->getLastUserIdBeforeRegistrationDate( $dbr, $to );
		if ( $this->hasOption( 'debug' ) ) {
			$this->error( "Converting registration timestamps:" );
			foreach ( [
				[ 'From (exclusive)', $from, $fromId ],
				[ 'To (inclusive)', $to, $toId ]
			] as [ $dir, $ts, $id ] ) {
				$text = 'any';
				if ( $id ) {
					$registered = $services->getUserFactory()->newFromId( $id )->getRegistration();
					$text = "UID $id (registered: $registered)";
				}
				$this->error( "\t$dir: $ts -> $text" );
			}
		}
		if ( $fromId === $toId ) {
			// There weren't any users between those two timestamps.
			return;
		}

		$queryBuilderTemplate = $dbr->newSelectQueryBuilder()
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
				'user_name',
				'user_registration',
				'user_email',
				'user_email_authenticated',
				'survey_data' => 'survey_prop.up_value',
				'homepage_enabled' => 'homepage_prop.up_value',
			] )
			// need to order by ID so we can use ID ranges for query continuation
			->orderBy( 'user_id', SelectQueryBuilder::SORT_ASC )
			->limit( $this->getBatchSize() )
			->caller( __METHOD__ );
		if ( $toId ) {
			$queryBuilderTemplate->where( $dbr->expr( 'user_id', '<=', $toId ) );
		}

		$userOptionsLookup = $services->getUserOptionsLookup();
		$this->handle = fopen( 'php://output', 'w' );
		$headers = [ 'Email Address', 'Opt-in date', 'Group', 'User ID', 'Is email address confirmed' ];
		$this->writeToHandle( $headers );

		$question = $this->getOption( 'question' );
		$group = $this->getOption( 'group' );
		do {
			$queryBuilder = clone $queryBuilderTemplate;
			$queryBuilder->andWhere( $dbr->expr( 'user_id', '>', $fromId ?? 0 ) );
			$result = $queryBuilder->fetchResultSet();
			foreach ( $result as $row ) {
				$fromId = $row->user_id;
				$homepageEnabled = (bool)(
					$row->homepage_enabled ??
					$userOptionsLookup->getDefaultOption(
						HomepageHooks::HOMEPAGE_PREF_ENABLE,
						new UserIdentityValue( $row->user_id, $row->user_name )
					)
				);
				$welcomeSurveyResponse = FormatJson::decode( (string)$row->survey_data, true );
				// We only want to export survey responses in the T303240_mailinglist group,
				// see https://gerrit.wikimedia.org/r/c/operations/mediawiki-config/+/775951
				// and only when the user gets the Growth features
				if ( !(
					$homepageEnabled &&
					$welcomeSurveyResponse &&
					( $group === null || $welcomeSurveyResponse['_group'] === $group )
				) ) {
					continue;
				}
				if ( isset( $welcomeSurveyResponse[$question] ) && $welcomeSurveyResponse[$question] ) {
					// user_email_authenticated is the timestamp of when the email was confirmed; we want a 1 or 0
					// to indicate if the email is confirmed.
					$outputData = [
						$row->user_email,
						wfTimestamp( TS_MW, $row->user_registration ),
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
	 * Given a registration date, return the ID of the user who last registered before that date.
	 * @param IReadableDatabase $dbr
	 * @param string $registrationDate
	 * @return int|null
	 */
	private function getLastUserIdBeforeRegistrationDate( IReadableDatabase $dbr, string $registrationDate ): ?int {
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'user_id' => 'max(user_id)' ] )
			->from( 'user' )
			// Old user records have no registration date. We won't use 'from' dates old enough
			// to encounter those so we can ignore them here.
			->where( $dbr->expr( 'user_registration', '<=', $registrationDate ) )
			->caller( __METHOD__ )
			->fetchField();
		return is_numeric( $res ) ? intval( $res ) : null;
	}

	private function writeToHandle( array $data ): void {
		if ( $this->outputFormat === 'text' ) {
			fputs( $this->handle, implode( "\t", $data ) . PHP_EOL );
		} else {
			fputcsv( $this->handle, $data, ',', '"', "\\" );
		}
	}
}

$maintClass = ExportWelcomeSurveyMailingListData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
