<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use Wikimedia\JsonCodec\JsonCodecable;
use Wikimedia\JsonCodec\JsonCodecableTrait;

/**
 * Class which contains the set of filters (task, topics) used to generate a TaskSet.
 *
 * JsonSerializable is implemented to provide the ability to compare TaskSetFilters across
 * TaskSets by JSON encoding the objects.
 */
class TaskSetFilters implements JsonCodecable {

	use JsonCodecableTrait;

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
	 * @var string|null Matching mode for topics. One of: 'AND', 'OR'.
	 * @see SearchStrategy::TOPIC_MATCH_MODES
	 */
	private $topicFiltersMode;

	/**
	 * @param string[] $taskTypeFilters
	 * @param string[] $topicFilters
	 * @param string|null $topicFiltersMode
	 */
	public function __construct(
		array $taskTypeFilters = [],
		array $topicFilters = [],
		?string $topicFiltersMode = null
	) {
		$this->taskTypeFilters = $taskTypeFilters;
		$this->topicFilters = $topicFilters;
		$this->topicFiltersMode = $topicFiltersMode ?? SearchStrategy::TOPIC_MATCH_MODE_OR;
	}

	public function getTopicFiltersMode(): string {
		return $this->topicFiltersMode;
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
	public function toJsonArray(): array {
		return [
			'task' => $this->taskTypeFilters,
			'topic' => $this->topicFilters,
			'topicMode' => $this->topicFiltersMode
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		return new static(
			$json['task'],
			$json['topic'],
			$json['topicMode'] ?? SearchStrategy::TOPIC_MATCH_MODE_OR
		);
	}

}
