<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Filter out protected items from a small resultset.
 */
class ProtectionFilter extends AbstractTaskSetFilter implements TaskSetFilter {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	private IConnectionProvider $connectionProvider;

	public function __construct(
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory,
		IConnectionProvider $connectionProvider
	) {
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->connectionProvider = $connectionProvider;
	}

	/** @inheritDoc */
	public function filter( TaskSet $taskSet, int $maxLength = PHP_INT_MAX ): TaskSet {
		// Warm title cache for fetching the IDs.
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		$linkBatch->setCaller( __METHOD__ );
		foreach ( $taskSet as $task ) {
			$linkBatch->addObj( $task->getTitle() );
		}
		$linkBatch->execute();

		$invalidTasks = [];
		$validTasks = [];

		foreach ( $taskSet as $task ) {
			$title = $this->titleFactory->newFromLinkTarget( $task->getTitle() );
			$validTasks[ $title->getArticleID() ] = $task;
		}
		// Do a single batch query instead of several individual queries with RestrictionStore.
		// In the longer run, adding batch querying to RestrictionStore itself would be nice.
		$results = [];
		if ( $validTasks ) {
			$results = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
				->select( [ 'pr_page' ] )
				->from( 'page_restrictions' )
				->where( [
					'pr_page' => array_keys( $validTasks ),
					'pr_type' => 'edit',
				] )
				->caller( __METHOD__ )
				->fetchResultSet();
		}

		foreach ( $results as $item ) {
			// We found restrictions, so add the task to the invalid task list, and
			// unset it from the valid task list.
			$invalidTasks[$item->pr_page] = $validTasks[$item->pr_page];
			unset( $validTasks[$item->pr_page] );
		}

		$validTasks = array_slice( $validTasks, 0, $maxLength, true );

		return $this->copyValidAndInvalidTasksToNewTaskSet( $taskSet, $validTasks, $invalidTasks );
	}

}
