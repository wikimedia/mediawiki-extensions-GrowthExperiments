<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
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
	 * Load the list of templates which prevent a page from ever becoming a task
	 * (meant for things like deletion templates).
	 * @return LinkTarget[]|StatusValue Set of configured templates, or an error status.
	 */
	public function loadTemplateBlacklist();

}
