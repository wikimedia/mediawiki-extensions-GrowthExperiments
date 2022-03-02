<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use MediaWiki\Json\JsonUnserializable;
use MediaWiki\Json\JsonUnserializableTrait;
use MediaWiki\Json\JsonUnserializer;

/**
 * Class which contains the set of filters (task, topics) used to generate a TaskSet.
 *
 * JsonSerializable is implemented to provide the ability to compare TaskSetFilters across
 * TaskSets by JSON encoding the objects.
 */
class TaskSetFilters implements JsonUnserializable {

	use JsonUnserializableTrait;

	/**
	 * @var string[] List of task type IDs to limit the suggestions to.
	 *   An empty array means no filtering.
	 */
	private $taskTypeFilters;
	/**
	 * @var string[] List of topic IDs to limit the suggestions to.
	 *   An empty array means no filtering.
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
	 * @param string[] $taskTypeFilters
	 */
	public function setTaskTypeFilters( array $taskTypeFilters ): void {
		$this->taskTypeFilters = $taskTypeFilters;
	}

	/**
	 * @return string[]
	 */
	public function getTaskTypeFilters(): array {
		return $this->taskTypeFilters;
	}

	/**
	 * @return string[]
	 */
	public function getTopicFilters(): array {
		return $this->topicFilters;
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return [
			'task' => $this->taskTypeFilters,
			'topic' => $this->topicFilters
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		return new self( $json['task'], $json['topic'] );
	}

}
