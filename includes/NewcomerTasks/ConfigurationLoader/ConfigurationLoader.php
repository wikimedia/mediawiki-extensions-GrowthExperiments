<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use StatusValue;

/**
 * Helper for retrieving task recommendation configuration.
 */
interface ConfigurationLoader {

	/**
	 * Load configured task types.
	 * @return TaskType[]|StatusValue Set of configured task types, or an error status.
	 */
	public function loadTaskTypes();

	/**
	 * Load configured infobox templates.
	 * @return string[]|StatusValue
	 */
	public function loadInfoboxTemplates();

	/**
	 * Convenience method to get task types as an array of task type id => task type.
	 *
	 * If an error is generated while loading task types, an empty array is
	 * returned.
	 *
	 * @return TaskType[]
	 */
	public function getTaskTypes(): array;

	/**
	 * Returns task types which have been disabled by configuration, keyed by task type ID.
	 * (loadTaskTypes() / getTaskTypes() will not return these.)
	 *
	 * Empty array when task type configuration fails to load.
	 *
	 * @return TaskType[]
	 */
	public function getDisabledTaskTypes(): array;
}
