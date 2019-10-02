<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
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

}
