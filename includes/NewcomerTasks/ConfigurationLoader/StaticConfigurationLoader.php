<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
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

	/** @var LinkTarget[]|StatusValue */
	private $excludedTemplates;

	/** @var LinkTarget[]|StatusValue */
	private $excludedCategories;

	/**
	 * @param TaskType[]|StatusValue $taskTypes
	 * @param Topic[]|StatusValue $topics
	 * @param LinkTarget[]|StatusValue $excludedTemplates
	 * @param LinkTarget[]|StatusValue $excludedCategories
	 */
	public function __construct(
		$taskTypes, $topics = [], $excludedTemplates = [], $excludedCategories = []
	) {
		$this->taskTypes = $taskTypes;
		$this->topics = $topics;
		$this->excludedTemplates = $excludedTemplates;
		$this->excludedCategories = $excludedCategories;
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
	public function loadExcludedTemplates() {
		return $this->excludedTemplates;
	}

	/** @inheritDoc */
	public function loadExcludedCategories() {
		return $this->excludedCategories;
	}

}
