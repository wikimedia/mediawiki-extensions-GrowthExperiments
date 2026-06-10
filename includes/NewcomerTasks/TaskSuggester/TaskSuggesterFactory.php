<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class TaskSuggesterFactory implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/**
	 * @param ConfigurationLoader|null $customConfigurationLoader Configuration loader to use instead of the default;
	 * used for querying different topic types (growth vs ores)
	 * @return TaskSuggester
	 */
	abstract public function create( ?ConfigurationLoader $customConfigurationLoader = null );

}
