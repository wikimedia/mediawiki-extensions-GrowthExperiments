<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\Cache\LinkBatchFactory;
use TitleFactory;
use Wikimedia\Rdbms\IDatabase;

/**
 * Filter out tasks which no longer have templates according to templatelinks table.
 *
 * It's possible that a cached TaskSet references articles which no longer have
 * the maintenance templates that caused them to be defined as newcomer tasks. This
 * class removes tasks which no longer have templates in templatelinks table.
 */
class TemplateFilter {

	/** @var IDatabase */
	private $dbr;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/**
	 * @param IDatabase $dbr
	 * @param TitleFactory $titleFactory
	 * @param LinkBatchFactory $linkBatchFactory
	 */
	public function __construct(
		IDatabase $dbr, TitleFactory $titleFactory, LinkBatchFactory $linkBatchFactory
	) {
		$this->dbr = $dbr;
		$this->titleFactory = $titleFactory;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * Filter out tasks which do not have templates stored in templatelinks.
	 *
	 * @param TaskSet $taskSet
	 * @return TaskSet
	 */
	public function filter( TaskSet $taskSet ) {
		// Warm title cache for fetching the IDs.
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		$linkBatch->setCaller( __METHOD__ );
		foreach ( $taskSet as $task ) {
			$linkBatch->addObj( $task->getTitle() );
		}
		$linkBatch->execute();

		$map = $this->buildResultsMap( $taskSet );

		$filteredTaskSet = new TaskSet(
			array_filter( iterator_to_array( $taskSet ),
				function ( Task $task ) use ( $map ) {
					$articleID = $this->titleFactory->newFromLinkTarget( $task->getTitle() )->getArticleID();
					if ( !$articleID ) {
						return false;
					}
					$taskType = $task->getTaskType();
					// Don't attempt to check non-template based tasks
					if ( !$taskType instanceof TemplateBasedTaskType ) {
						return true;
					}
					// The article isn't in the map, so all the templates associated with the
					// task type were deleted.
					if ( !isset( $map[$articleID] ) ) {
						return false;
					}
					$templateList = array_map( function ( $template ) {
						return $template->getDBkey();
					}, $taskType->getTemplates() );
					// Check to see that at least one of the templates we think should be present
					// for the article is present in the mapping.
					return array_intersect( $templateList, $map[$articleID] );
				} ),
			$taskSet->getTotalCount(),
			$taskSet->getOffset(),
			$taskSet->getFilters()
		);
		$filteredTaskSet->setDebugData( $taskSet->getDebugData() );
		return $filteredTaskSet;
	}

	/**
	 * @param TaskSet $taskSet
	 * @return array[]
	 */
	private function buildQueryConds( TaskSet $taskSet ): array {
		$conds = [];
		foreach ( $taskSet as $task ) {
			if ( !$task->getTaskType() instanceof TemplateBasedTaskType ) {
				// Ignore non-template based tasks here.
				continue;
			}
			$articleID = $this->titleFactory->newFromLinkTarget( $task->getTitle() )->getArticleID();
			$templateList = array_map( function ( $template ) {
				return $template->getDBkey();
			}, $task->getTaskType()->getTemplates() );
			if ( !count( $templateList ) ) {
				// Sanity - empty arrays make the query builder unhappy.
				continue;
			}

			$conds[] = $this->dbr->makeList( [
				'tl_from' => $articleID,
				'tl_title' => $templateList,
				'tl_namespace' => NS_TEMPLATE,
			], $this->dbr::LIST_AND );
		}

		return $conds;
	}

	/**
	 * For each task in a task set, lists all the templates present on the task's article page
	 * that are valid for that task type.
	 * @param TaskSet $taskSet
	 * @return array [ page id => [ template title, ... ] ]. Titles are without namespace.
	 */
	private function buildResultsMap( TaskSet $taskSet ): array {
		$conds = $this->buildQueryConds( $taskSet );
		if ( !$conds ) {
			// Needs to be special-cased, would turn into selecting the whole table.
			return [];
		}

		$result = $this->dbr->select(
			'templatelinks',
			[ 'tl_from', 'tl_title' ],
			$this->dbr->makeList( $conds, $this->dbr::LIST_OR ),
			__METHOD__
		);
		$map = [];
		foreach ( $result as $row ) {
			$map[$row->tl_from][] = $row->tl_title;
		}
		return $map;
	}
}
