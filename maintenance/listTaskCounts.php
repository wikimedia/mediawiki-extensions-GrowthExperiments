<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use Maintenance;
use MediaWiki\MediaWikiServices;
use Status;
use StatusValue;
use User;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

/**
 * List the number of tasks available for each topic
 */
class ListTaskCounts extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'List the number of tasks available for each topic.' );
		$this->addOption( 'tasktype', 'Task types to query, specify multiple times for multiple ' .
				'task types. Defaults to all task types.',
			false, true, false, true );
	}

	/** @inheritDoc */
	public function execute() {
		$services = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$taskSuggester = $services->getTaskSuggesterFactory()->create();
		$dummyUser = new User;
		$allTaskTypes = array_keys( $services->getNewcomerTasksConfigurationLoader()->getTaskTypes() );
		$taskTypes = $this->getOption( 'tasktype', $allTaskTypes );
		$topicIds = array_keys( $services->getNewcomerTasksConfigurationLoader()->getTopics() );

		// Output header
		$this->output( str_pad( 'Topic', 25, ' ' ) . ' ' );
		foreach ( $taskTypes as $taskType ) {
			$this->output( str_pad( $taskType, 10, ' ' ) . ' ' );
		}
		$this->output( "\n" . str_repeat( '-', 80 ) . "\n" );

		foreach ( $topicIds as $topicId ) {
			$this->output( str_pad( $topicId, 25, ' ' ) . ' ' );
			foreach ( $taskTypes as $taskType ) {
				$tasks = $taskSuggester->suggest( $dummyUser, [ $taskType ], [ $topicId ], 1 );
				if ( $tasks instanceof StatusValue ) {
					$this->fatalError( Status::wrap( $tasks )->getWikiText( null, null, 'en' ) );
				}
				$numTasks = $tasks->getTotalCount();
				$this->output( str_pad( $numTasks, 3, ' ', STR_PAD_RIGHT ) . str_repeat( ' ', 8 ) );
			}
			$this->output( "\n" );
		}
	}

}

$maintClass = ListTaskCounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
