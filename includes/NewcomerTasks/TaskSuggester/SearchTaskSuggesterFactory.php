<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use Psr\Log\NullLogger;

abstract class SearchTaskSuggesterFactory extends TaskSuggesterFactory {

	/** @var ConfigurationLoader */
	protected $configurationLoader;

	/** @var SearchStrategy */
	protected $searchStrategy;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy
	) {
		$this->configurationLoader = $configurationLoader;
		$this->searchStrategy = $searchStrategy;
		$this->logger = new NullLogger();
	}

}
