<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
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
}
