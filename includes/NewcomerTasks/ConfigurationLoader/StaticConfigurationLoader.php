<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use StatusValue;

/**
 * A minimal ConfigurationLoader for testing and development which returns preconfigured values.
 */
class StaticConfigurationLoader implements ConfigurationLoader {

	use ConfigurationLoaderTrait;

	/** @var TaskType[]|StatusValue */
	private $taskTypes;

	/** @var Topic[]|StatusValue */
	private $topics;

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 */
	public function __construct( $taskTypes, $topics = [] ) {
		$this->taskTypes = $taskTypes;
		$this->topics = $topics;
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		return $this->taskTypes;
	}

	/** @inheritDoc */
	public function loadTopics() {
		return $this->topics;
	}

	/** @inheritDoc */
	public function getDisabledTaskTypes(): array {
		return [];
	}

}
