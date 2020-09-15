<?php

namespace GrowthExperiments\NewcomerTasks\Task;

/**
 * Class which contains the set of filters (task, topics) used to generate a TaskSet.
 *
 * JsonSerializable is implemented to provide the ability to compare TaskSetFilters across
 * TaskSets by JSON encoding the objects.
 */
class TaskSetFilters implements \JsonSerializable {

	/**
	 * @var string[]
	 */
	private $taskTypeFilters;
	/**
	 * @var string[]
	 */
	private $topicFilters;

	/**
	 * @param string[] $taskTypeFilters
	 * @param string[] $topicFilters
	 */
	public function __construct( array $taskTypeFilters = [], array $topicFilters = [] ) {
		$this->taskTypeFilters = $taskTypeFilters;
		$this->topicFilters = $topicFilters;
	}

	/**
	 * @return string[]
	 */
	public function getTopicFilters() : array {
		return $this->topicFilters;
	}

	/**
	 * @return string[]
	 */
	public function getTaskTypeFilters() : array {
		return $this->taskTypeFilters;
	}

	public function jsonSerialize() {
		return [
			'task' => $this->taskTypeFilters,
			'topic' => $this->topicFilters
		];
	}
}
