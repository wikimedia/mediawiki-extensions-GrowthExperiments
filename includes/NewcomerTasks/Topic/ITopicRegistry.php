<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

interface ITopicRegistry {
	/**
	 * Get all topics.
	 * @return Topic[] A flat array of topics
	 */
	public function getTopics(): array;

	/**
	 * Convenience method to get topics as an array of topic id => topic.
	 *
	 * @return Topic[] Array of topic id => topic
	 */
	public function getTopicsMap(): array;
}
