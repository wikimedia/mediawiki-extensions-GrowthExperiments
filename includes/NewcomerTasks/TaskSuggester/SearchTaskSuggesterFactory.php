<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use Psr\Log\NullLogger;

abstract class SearchTaskSuggesterFactory extends TaskSuggesterFactory {

	/** @var ConfigurationLoader */
	protected $configurationLoader;

	/** @var SearchStrategy */
	protected $searchStrategy;

	/** @var TemplateProvider */
	protected $templateProvider;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param TemplateProvider $templateProvider
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		TemplateProvider $templateProvider
	) {
		$this->configurationLoader = $configurationLoader;
		$this->searchStrategy = $searchStrategy;
		$this->templateProvider = $templateProvider;
		$this->logger = new NullLogger();
	}

}
