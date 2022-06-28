<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use MediaWiki\Cache\LinkBatchFactory;
use TitleFactory;
use Wikimedia\Rdbms\IDatabase;

/**
 * Filter out protected items from a small resultset.
 */
class ProtectionFilter extends AbstractTaskSetFilter implements TaskSetFilter {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var IDatabase */
	private $dbr;

	/**
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param IDatabase $dbr
	 */
	public function __construct(
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory,
		IDatabase $dbr
	) {
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->dbr = $dbr;
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
			$results = $this->dbr->select(
				'page_restrictions',
				[ 'pr_page' ],
				[
					'pr_page' => array_keys( $validTasks ),
					'pr_type' => 'edit'
				],
				__METHOD__
			);
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
