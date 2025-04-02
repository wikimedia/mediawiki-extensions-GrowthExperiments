<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use StatusValue;

/**
 * A minimal ConfigurationLoader for testing and development which returns preconfigured values.
 */
class StaticConfigurationLoader implements ConfigurationLoader {

	use ConfigurationLoaderTrait;

	/** @var TaskType[]|StatusValue */
	private $taskTypes;

	/** @var string[]|StatusValue */
	private $infoboxTemplates;

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param string[]|StatusValue $infoboxTemplates
	 */
	public function __construct( $taskTypes, $infoboxTemplates = [] ) {
		$this->taskTypes = $taskTypes;
		$this->infoboxTemplates = $infoboxTemplates;
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		return $this->taskTypes;
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
