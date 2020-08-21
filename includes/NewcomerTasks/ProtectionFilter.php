<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use MediaWiki\Cache\LinkBatchFactory;
use TitleFactory;

/**
 * Filter out protected items from a small resultset.
 */
class ProtectionFilter {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/**
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 */
	public function __construct(
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * Filter out protected tasks from the TaskSet. Order is preserved.
	 * This is not particularly efficient; the taskset should not have more than a few tasks.
	 * @param TaskSet $taskSet
	 * @param int $maxLength Return at most this many tasks (used to avoid wasting time on
	 *   checking tasks we won't need).
	 * @return TaskSet
	 */
	public function filter( TaskSet $taskSet, int $maxLength = PHP_INT_MAX ) {
		// Warm title cache for fetching the IDs.
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		$linkBatch->setCaller( __METHOD__ );
		foreach ( $taskSet as $task ) {
			$linkBatch->addObj( $task->getTitle() );
		}
		$linkBatch->execute();

		$tasks = [];
		foreach ( $taskSet as $task ) {
			if ( count( $tasks ) >= $maxLength ) {
				break;
			}
			$title = $this->titleFactory->newFromLinkTarget( $task->getTitle() );
			// isProtected is not covered by the LinkBatch. For now we only need filtering
			// for single-task lookups so constructing our own efficient SQL query is not
			// worth the effort.
			// Keep titles which do not exist. This is useful for local test setups.
			if ( !$title->exists() || !$title->isProtected( 'edit' ) ) {
				$tasks[] = $task;
			}
		}
		$filteredTaskSet = new TaskSet( $tasks, $taskSet->getTotalCount(), $taskSet->getOffset() );
		$filteredTaskSet->setDebugData( $taskSet->getDebugData() );
		return $filteredTaskSet;
	}

}
