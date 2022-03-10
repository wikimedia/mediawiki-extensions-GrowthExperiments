<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\User\UserIdentity;
use TitleFactory;
use Wikimedia\Assert\Assert;

/**
 * A TaskSuggester which always starts with the same preconfigured set of tasks, and applies
 * filter/limit/offset to them. Intended for testing and local frontend development.
 */
class StaticTaskSuggester implements TaskSuggester {

	/** @var Task[] */
	private $tasks;
	/**
	 * @var TitleFactory|null
	 */
	private $titleFactory;

	/**
	 * @param Task[] $tasks
	 * @param TitleFactory|null $titleFactory
	 */
	public function __construct( array $tasks, ?TitleFactory $titleFactory = null ) {
		Assert::parameterElementType( Task::class, $tasks, '$suggestions' );
		$this->tasks = $tasks;
		$this->titleFactory = $titleFactory;
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	) {
		$filteredTasks = array_filter( $this->tasks,
			function ( Task $task ) use ( $taskSetFilters, $options ) {
				if ( isset( $options['excludePageIds'] ) && $this->titleFactory instanceof TitleFactory ) {
					$title = $this->titleFactory->castFromLinkTarget( $task->getTitle() );
					if ( in_array( $title->getArticleID(), $options['excludePageIds'] ) ) {
						return false;
					}
				}
				$taskTypeFilter = $taskSetFilters->getTaskTypeFilters();
				$topicFilter = $taskSetFilters->getTopicFilters();
				if ( $taskTypeFilter && !in_array( $task->getTaskType()->getId(), $taskTypeFilter, true ) ) {
					return false;
				} elseif ( $topicFilter && !array_intersect( $this->getTopicIds( $task ), $topicFilter ) ) {
					return false;
				}
				return true;
			}
		);
		return new TaskSet( array_slice( $filteredTasks, $offset ?? 0, $limit ),
			count( $filteredTasks ), $offset ?: 0, new TaskSetFilters() );
	}

	/** @inheritDoc */
	public function filter( UserIdentity $user, TaskSet $taskSet ) {
		$tasks = array_filter( iterator_to_array( $taskSet ), function ( Task $task ) {
			return in_array( $task, $this->tasks, true );
		} );
		$newTaskSet = new TaskSet( $tasks, $taskSet->getTotalCount(), $taskSet->getOffset(),
			$taskSet->getFilters() );
		$newTaskSet->setDebugData( $taskSet->getDebugData() );
		return $newTaskSet;
	}

	/**
	 * @param Task $task
	 * @return string[]
	 */
	private function getTopicIds( Task $task ) {
		return array_map( static function ( Topic $topic ) {
			return $topic->getId();
		}, $task->getTopics() );
	}

}
