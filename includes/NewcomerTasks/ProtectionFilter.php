<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Permissions\RestrictionStore;
use TitleFactory;

/**
 * Filter out protected items from a small resultset.
 */
class ProtectionFilter extends AbstractTaskSetFilter implements TaskSetFilter {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var RestrictionStore */
	private $restrictionStore;

	/**
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param RestrictionStore $restrictionStore
	 */
	public function __construct(
		TitleFactory $titleFactory,
		LinkBatchFactory $linkBatchFactory,
		RestrictionStore $restrictionStore
	) {
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->restrictionStore = $restrictionStore;
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
			if ( count( $validTasks ) >= $maxLength ) {
				break;
			}
			$title = $this->titleFactory->newFromLinkTarget( $task->getTitle() );
			// isProtected is not covered by the LinkBatch. For now we only need filtering
			// for single-task lookups so constructing our own efficient SQL query is not
			// worth the effort.
			// Keep titles which do not exist. This is useful for local test setups.
			if ( !$title->exists() || !$this->restrictionStore->isProtected( $title, 'edit' ) ) {
				$validTasks[] = $task;
			} else {
				$invalidTasks[] = $task;
			}
		}

		return $this->copyValidAndInvalidTasksToNewTaskSet( $taskSet, $validTasks, $invalidTasks );
	}

}
