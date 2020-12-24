<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
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
	 * Load configured topics.
	 * @return Topic[]|StatusValue
	 */
	public function loadTopics();

	/**
	 * Load the list of templates which prevent a page from ever becoming a task
	 * (meant for things like deletion templates).
	 * @return LinkTarget[]|StatusValue Set of configured templates, or an error status.
	 */
	public function loadExcludedTemplates();

	/**
	 * Load the list of categories which prevent a page from ever becoming a task.
	 * @return LinkTarget[]|StatusValue Set of configured categories, or an error status.
	 */
	public function loadExcludedCategories();

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
	 * Convenience method to get topics as an array of topic id => topic.
	 *
	 * If an error is generated while loading, an empty array is returned.
	 *
	 * @return Topic[]
	 */
	public function getTopics(): array;

	/**
	 * Convenience method to get the list of templates which prevent a page from ever becoming
	 * a task (meant for things like deletion templates) without need for error handling.
	 *
	 * If an error is generated while loading, an empty array is returned.
	 *
	 * @return LinkTarget[] Set of configured templates.
	 */
	public function getExcludedTemplates();

	/**
	 * Convenience method to get the list of categories which prevent a page from ever
	 * becoming a task, without need for error handling.
	 *
	 * If an error is generated while loading, an empty array is returned.
	 *
	 * @return LinkTarget[] Set of configured categories.
	 */
	public function getExcludedCategories();

}
