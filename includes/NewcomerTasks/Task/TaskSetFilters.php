<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use Wikimedia\Assert\Assert;
use Wikimedia\JsonCodec\JsonCodecable;
use Wikimedia\JsonCodec\JsonCodecableTrait;

/**
 * Class which contains the set of filters (task, topics, interests) used to generate a TaskSet.
 *
 * JsonSerializable is implemented to provide the ability to compare TaskSetFilters across
 * TaskSets by JSON encoding the objects.
 */
class TaskSetFilters implements JsonCodecable {

	use JsonCodecableTrait;

	private string $topicFiltersMode;

	/**
	 * @param string[] $taskTypeFilters List of task type IDs to limit the suggestions to.
	 *   An empty array means no filtering.
	 * @param string[] $topicFilters List of topic IDs to limit the suggestions to.
	 *   An empty array means no filtering. Mutually exclusive with $interestFilters.
	 * @param string|null $topicFiltersMode Matching mode for topics. One of: 'AND', 'OR'.
	 *   See SearchStrategy::TOPIC_MATCH_MODES.
	 * @param string[] $interestFilters List of interests (prefixed article titles) to limit
	 *   the suggestions to. An empty array means no filtering. Mutually exclusive with
	 *   $topicFilters.
	 */
	public function __construct(
		private array $taskTypeFilters = [],
		private array $topicFilters = [],
		?string $topicFiltersMode = null,
		private array $interestFilters = []
	) {
		Assert::parameter(
			!$topicFilters || !$interestFilters,
			'$interestFilters',
			'topic filters and interest filters are mutually exclusive'
		);
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

	/**
	 * @return string[] List of interests (prefixed article titles).
	 */
	public function getInterestFilters(): array {
		return $this->interestFilters;
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		$json = [
			'task' => $this->taskTypeFilters,
			'topic' => $this->topicFilters,
			'topicMode' => $this->topicFiltersMode,
		];
		// Only include the key when interests are set, to keep the serialization
		// of topic-based filters unchanged.
		if ( $this->interestFilters ) {
			$json['interests'] = $this->interestFilters;
		}
		return $json;
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		return new static(
			$json['task'],
			$json['topic'],
			$json['topicMode'] ?? SearchStrategy::TOPIC_MATCH_MODE_OR,
			$json['interests'] ?? []
		);
	}

}
