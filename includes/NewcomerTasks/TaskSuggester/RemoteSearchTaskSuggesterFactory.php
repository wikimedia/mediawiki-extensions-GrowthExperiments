<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use MediaWiki\Http\HttpRequestFactory;
use StatusValue;
use TitleFactory;

/**
 * Factory for RemoteSearchTaskSuggester.
 */
class RemoteSearchTaskSuggesterFactory extends SearchTaskSuggesterFactory {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var string */
	private $apiUrl;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param string $apiUrl Base URL of the remote API (ending with 'api.php').
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		string $apiUrl
	) {
		parent::__construct( $configurationLoader, $searchStrategy );
		$this->requestFactory = $requestFactory;
		$this->titleFactory = $titleFactory;
		$this->apiUrl = $apiUrl;
	}

	/**
	 * @return RemoteSearchTaskSuggester|ErrorForwardingTaskSuggester
	 */
	public function create() {
		$taskTypes = $this->configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return $this->createError( $taskTypes );
		}
		$topics = $this->configurationLoader->loadTopics();
		if ( $topics instanceof StatusValue ) {
			return $this->createError( $topics );
		}
		$templateBlacklist = $this->configurationLoader->loadTemplateBlacklist();
		if ( $templateBlacklist instanceof StatusValue ) {
			return $this->createError( $templateBlacklist );
		}
		$suggester = new RemoteSearchTaskSuggester(
			$this->searchStrategy,
			$this->requestFactory,
			$this->titleFactory,
			$this->apiUrl,
			$taskTypes,
			$topics,
			$templateBlacklist
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

}
