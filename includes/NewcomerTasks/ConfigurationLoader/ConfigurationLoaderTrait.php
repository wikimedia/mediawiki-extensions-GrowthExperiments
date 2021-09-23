<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use StatusValue;

/**
 * Code share helper for ConfigurationLoader subclasses.
 */
trait ConfigurationLoaderTrait {

	/**
	 * Load configured task types.
	 * @return TaskType[]|StatusValue Set of configured task types, or an error status.
	 */
	abstract public function loadTaskTypes();

	/**
	 * Load configured topics.
	 * @return Topic[]|StatusValue
	 */
	abstract public function loadTopics();

	/**
	 * Convenience method to get task types as an array of task type id => task type.
	 *
	 * If an error is generated while loading task types, an empty array is
	 * returned.
	 *
	 * @return TaskType[]
	 */
	public function getTaskTypes(): array {
		$taskTypes = $this->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return [];
		}
		return array_combine( array_map( static function ( TaskType $taskType ) {
			return $taskType->getId();
		}, $taskTypes ), $taskTypes );
	}

	/**
	 * Convenience method to get topics as an array of topic id => topic.
	 *
	 * If an error is generated while loading, an empty array is returned.
	 *
	 * @return Topic[]
	 */
	public function getTopics(): array {
		$topics = $this->loadTopics();
		if ( $topics instanceof StatusValue ) {
			return [];
		}
		return array_combine( array_map( static function ( Topic $topic ) {
			return $topic->getId();
		}, $topics ), $topics );
	}

}
