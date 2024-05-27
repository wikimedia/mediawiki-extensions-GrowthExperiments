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

	/** @var string[]|StatusValue */
	private $infoboxTemplates;

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @param string[]|StatusValue $infoboxTemplates
	 */
	public function __construct( $taskTypes, $topics = [], $infoboxTemplates = [] ) {
		$this->taskTypes = $taskTypes;
		$this->topics = $topics;
		$this->infoboxTemplates = $infoboxTemplates;
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

	/** @inheritDoc */
	public function loadInfoboxTemplates() {
		return $this->infoboxTemplates;
	}
}
