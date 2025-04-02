<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

class StaticTopicRegistry implements ITopicRegistry {
	/** @var Topic[]|null */
	private ?array $topics;

	/**
	 * @param Topic[] $topics
	 */
	public function __construct( array $topics = [] ) {
		$this->topics = $topics;
	}

	public function loadTopics(): array {
		return $this->topics;
	}

	public function getTopics(): array {
		return $this->topics;
	}
}
