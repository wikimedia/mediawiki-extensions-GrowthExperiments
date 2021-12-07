<?php

namespace GrowthExperiments\Maintenance;

use ChangeTags;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\StructuredTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionStore;
use Status;
use StatusValue;
use stdClass;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class FixSuggestedEditChangeTags extends Maintenance {

	/** @var IDatabase */
	private $dbr;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var User User to perform the actions with. */
	private $user;

	/** @var TaskType The task type to work on. */
	private $taskType;

	/** @var TaskTypeHandler The handler of task type to work on. */
	private $taskTypeHandler;

	/** @var string The log_action value associated with this task type. */
	private $logAction;

	/** @var string The change tag name associated with this task type. */
	private $changeTagName;

	/** @var int The change tag ID associated with this task type. */
	private $changeTagId;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Remove change tags which were added in error (T296818)' );
		$this->addOption( 'tasktype', 'Task type', true, true );
		$this->addOption( 'from', 'Revision ID to continue from', false, true );
		$this->addOption( 'fix', 'Make changes (default is dry run)' );
		$this->addOption( 'verbose', 'Verbose mode (list fixed titles)', false, false, 'v' );
		$this->setBatchSize( 100 );
	}

	private function initialize() {
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );

		$this->dbr = $this->getDB( DB_REPLICA );
		$this->revisionStore = $services->getRevisionStore();
		$this->user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );

		$taskTypeId = $this->getOption( 'tasktype' );
		$configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$taskTypes = $configurationLoader->getTaskTypes() ?: $configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			$this->fatalError( Status::wrap( $taskTypes )->getWikiText( false, false, 'en' ) );
		} elseif ( !array_key_exists( $taskTypeId, $taskTypes ) ) {
			$this->fatalError( "Invalid task type $taskTypeId" );
		}
		$this->taskType = $taskTypes[$taskTypeId];
		$this->taskTypeHandler = $growthServices->getTaskTypeHandlerRegistry()
			->getByTaskType( $this->taskType );
		if ( !( $this->taskTypeHandler instanceof StructuredTaskTypeHandler ) ) {
			$this->fatalError( "$taskTypeId is not a structured task type" );
		}

		$this->logAction = [
			'link-recommendation' => 'addlink',
			'image-recommendation' => 'addimage',
		][$taskTypeId];
		$this->changeTagName = [
			'link-recommendation' => LinkRecommendationTaskTypeHandler::CHANGE_TAG,
			'image-recommendation' => ImageRecommendationTaskTypeHandler::CHANGE_TAG,
		][$taskTypeId];
		$changeTagDefStore = $services->getChangeTagDefStore();
		$this->changeTagId = $changeTagDefStore->getId( $this->changeTagName );
	}

	/** @inheritDoc */
	public function execute() {
		$this->initialize();
		$fromRevision = (int)$this->getOption( 'from', 0 );

		$fixedRevisions = 0;
		do {
			$this->output( "Fetching batch from revision $fromRevision\n" );
			$queryBuilder = $this->getStructuredEditTagsQuery( $this->dbr, $this->getBatchSize(),
				$fromRevision );
			$res = $queryBuilder->fetchResultSet();
			foreach ( $res as $row ) {
				$this->processRow( $row, $fixedRevisions );
				$fromRevision = $row->rev_id + 1;
			}
			$this->waitForReplication();
		} while ( $res->numRows() );
		$this->output(
			( $this->hasOption( 'fix' ) ? 'Fixed' : 'Would have fixed' )
			. " $fixedRevisions revisions\n"
		);
	}

	/**
	 * Get a query for (one page of) all revisions with structured edit change tags.
	 * @param IDatabase $dbr
	 * @param int $limit Number of rows the query should return.
	 * @param int $fromRevision Revision to start from (ascending).
	 * @return SelectQueryBuilder
	 */
	private function getStructuredEditTagsQuery(
		IDatabase $dbr,
		int $limit,
		int $fromRevision
	): SelectQueryBuilder {
		// Get a basic revision query.
		$queryInfo = $this->revisionStore->getQueryInfo();
		$queryBuilder = $dbr->newSelectQueryBuilder()->queryInfo( $queryInfo );

		// Join with change tags and filter to the selected tag.
		// ChangeTags::modifyDisplayQuery() would make a complicated query to concatenate all
		// tags; we don't need that so not worth using it.
		$queryBuilder->join( 'change_tag', null, [
			'rev_id = ct_rev_id',
			'ct_tag_id' => $this->changeTagId,
		] );

		// Join with the relevant log events.
		// We only associate the log event with the revision on edit, not rejection, so
		// this will be a one-to-one relationship.
		$queryBuilder->leftJoin(
			$queryBuilder->newJoinGroup()
				->table( 'logging' )
				->join( 'log_search', null, [
					'ls_log_id = log_id',
					'ls_field' => 'associated_rev_id',
				] ),
			null,
			[
				'ls_value = rev_id',
				'log_type' => 'growthexperiments',
				'log_action' => [ 'addlink', 'addimage' ],
			]
		);
		$queryBuilder->field( 'log_action' );

		// Handle paging.
		// We have to make use of the rather unhelpful (ct_tag_id, ct_rc_id, ct_rev_id, ct_log_id)
		// index, otherwise the query would have to go through all revisions.
		// We can choose between paginating by revision ID, which is a filesort, or paginating
		// by RC id, which makes the --from flag less useful. For now we go with the first
		// as the number of tagged revisions is expected to be small.
		$queryBuilder->conds( 'ct_rev_id >= ' . $fromRevision );
		$queryBuilder->orderBy( 'ct_tag_id ASC, ct_rev_id ASC' );
		$queryBuilder->limit( $limit );

		return $queryBuilder;
	}

	private function processRow( stdClass $row, int &$fixedRevisions ) {
		if ( $row->log_action != $this->logAction ) {
			// Revision with a structured edit change tag but no associated log entry.
			// Log entries are way more reliable (and we couldn't retroactively change log entries
			// anyway), remove the change tag.
			if ( $this->hasOption( 'fix' ) ) {
				ChangeTags::updateTags( null, $this->changeTagName, $rc_id, $row->rev_id, $log_id,
					null, null, $this->user );
			}
			if ( $this->hasOption( 'verbose' ) ) {
				$diffLink = wfExpandUrl( wfAppendQuery( wfScript(), [
					'oldid' => $row->rev_id,
					'diff' => 'prev',
				] ), PROTO_CANONICAL );
				$verb = $this->hasOption( 'fix' ) ? 'Removing' : 'Would remove';
				$this->output( "$verb tag for $diffLink\n" );
			}
			$fixedRevisions++;
		}
	}

}

$maintClass = FixSuggestedEditChangeTags::class;
require_once RUN_MAINTENANCE_IF_MAIN;
