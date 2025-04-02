<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

interface ITopicRegistry {
	/**
	 * Load all topics.
	 * @return Topic[]
	 */
	public function loadTopics(): array;

	/**
	 * Convenience method to get topics as an array of topic id => topic.
	 *
	 * If an error is generated while loading, an empty array is returned.
	 *
	 * @return Topic[] Array of topic id => topic
	 */
	public function getTopics(): array;
}
