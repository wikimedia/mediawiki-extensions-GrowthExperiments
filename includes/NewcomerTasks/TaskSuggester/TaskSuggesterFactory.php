<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use SearchEngineFactory;
use Status;
use StatusValue;
use TitleFactory;

class TaskSuggesterFactory implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var SearchStrategy */
	private $searchStrategy;

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

	/**
	 * Create a TaskSuggester which uses a public search API.
	 * @param TemplateProvider $templateProvider
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param string $apiUrl Base URL of the remote API (ending with 'api.php').
	 * @return TaskSuggester
	 */
	public function createRemote(
		TemplateProvider $templateProvider,
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		$apiUrl
	) {
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
			$templateProvider,
			$this->searchStrategy,
			$requestFactory,
			$titleFactory,
			$apiUrl,
			$taskTypes,
			$topics,
			$templateBlacklist
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

	/**
	 * Create a TaskSuggester which uses the local search API.
	 *
	 * @param SearchEngineFactory $searchEngineFactory
	 * @param TemplateProvider $templateProvider
	 * @return TaskSuggester
	 */
	public function createLocal(
		SearchEngineFactory $searchEngineFactory,
		TemplateProvider $templateProvider
	) {
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
		$suggester = new LocalSearchTaskSuggester(
			$searchEngineFactory,
			$templateProvider,
			$this->searchStrategy,
			$taskTypes,
			$topics,
			$templateBlacklist
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

	/**
	 * Create a TaskSuggester which just returns a given error.
	 * @param StatusValue $status
	 * @return ErrorForwardingTaskSuggester
	 */
	protected function createError( StatusValue $status ) {
		$msg = Status::wrap( $status )->getWikiText( false, false, 'en' );
		Util::logError( new WikiConfigException( $msg ) );
		return new ErrorForwardingTaskSuggester( $status );
	}

}
