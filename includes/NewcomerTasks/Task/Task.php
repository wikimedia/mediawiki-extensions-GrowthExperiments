<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;

/**
 * A single task recommendation.
 * A Task specifies a page and the type of the task to perform on it.
 */
class Task {

	/** @var TaskType */
	private $taskType;

	/** @var LinkTarget The page to edit. */
	private $title;

	/** @var Topic[] */
	private $topics = [];

	/**
	 * @param TaskType $taskType
	 * @param LinkTarget $title The page this task is about.
	 */
	public function __construct( TaskType $taskType, LinkTarget $title ) {
		$this->taskType = $taskType;
		$this->title = $title;
	}

	/**
	 * @return TaskType
	 */
	public function getTaskType(): TaskType {
		return $this->taskType;
	}

	/**
	 * @return LinkTarget
	 */
	public function getTitle(): LinkTarget {
		return $this->title;
	}

	/**
	 * Topics of the underlying article. Depending on the how topics are implemented, this
	 * might be never set, even if the TaskSuggester otherwise supports topic search.
	 * @return Topic[]
	 */
	public function getTopics(): array {
		return $this->topics;
	}

	/**
	 * @param Topic[] $topics
	 */
	public function setTopics( array $topics ): void {
		$this->topics = $topics;
	}

}
