<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

interface ITopicRegistry {
	/**
	 * Returns a plain list of topic IDs, for validation and the like.
	 * @return string[]
	 * @phan-return list<string>
	 */
	public function getAllTopics(): array;
}
