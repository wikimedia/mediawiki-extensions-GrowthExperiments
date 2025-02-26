<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

class EmptyTopicRegistry implements ITopicRegistry {

	public function getAllTopics(): array {
		return [];
	}
}
