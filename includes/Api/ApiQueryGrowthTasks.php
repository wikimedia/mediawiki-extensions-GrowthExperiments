<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiPageSet;
use ApiQuery;
use ApiQueryGeneratorBase;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use StatusValue;
use Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * API endpoint for Newcomer Tasks feature.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks
 */
class ApiQueryGrowthTasks extends ApiQueryGeneratorBase {

	/** @var TaskSuggesterFactory */
	private $taskSuggesterFactory;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var LinkRecommendationFilter */
	private $linkRecommendationFilter;

	/** @var ImageRecommendationFilter */
	private $imageRecommendationFilter;
	/**
	 * @var ProtectionFilter
	 */
	private $protectionFilter;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param ConfigurationLoader $configurationLoader
	 * @param LinkRecommendationFilter $linkRecommendationFilter
	 * @param ImageRecommendationFilter $imageRecommendationFilter
	 * @param ProtectionFilter $protectionFilter
	 */
	public function __construct(
		ApiQuery $queryModule,
		$moduleName,
		TaskSuggesterFactory $taskSuggesterFactory,
		ConfigurationLoader $configurationLoader,
		LinkRecommendationFilter $linkRecommendationFilter,
		ImageRecommendationFilter $imageRecommendationFilter,
		ProtectionFilter $protectionFilter
	) {
		parent::__construct( $queryModule, $moduleName, 'gt' );
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->configurationLoader = $configurationLoader;
		$this->linkRecommendationFilter = $linkRecommendationFilter;
		$this->imageRecommendationFilter = $imageRecommendationFilter;
		$this->protectionFilter = $protectionFilter;
	}

	/** @inheritDoc */
	public function execute() {
		$this->run();
	}

	/** @inheritDoc */
	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	/**
	 * @param ApiPageSet|null $resultPageSet
	 */
	protected function run( ApiPageSet $resultPageSet = null ) {
		$user = $this->getUser();
		$params = $this->extractRequestParams();
		$taskTypes = $params['tasktypes'];
		$topics = $params['topics'];
		$topicsMode = $params['topicsmode'];
		$limit = $params['limit'];
		$offset = $params['offset'];
		$debug = $params['debug'];
		$excludePageIds = $params['excludepageids'] ?? [];

		$taskSuggester = $this->taskSuggesterFactory->create();
		$taskTypeFilter = new TaskSetFilters( $taskTypes, $topics, $topicsMode );

		/** @var TaskSet $tasks */
		$tasks = $taskSuggester->suggest(
			$user,
			$taskTypeFilter,
			$limit,
			$offset,
			[
				'debug' => $debug,
				'excludePageIds' => $excludePageIds,
				// Don't use the cache if exclude page IDs has been provided;
				// the page IDs are supplied if we are attempting to load more
				// tasks into the queue in the front end.
				'useCache' => !(bool)$excludePageIds,
			]
		);
		if ( $tasks instanceof StatusValue ) {
			$this->dieStatus( $tasks );
		}

		$tasks = $this->linkRecommendationFilter->filter( $tasks );
		$tasks = $this->imageRecommendationFilter->filter( $tasks );
		$tasks = $this->protectionFilter->filter( $tasks );

		$result = $this->getResult();
		$basePath = [ 'query', $this->getModuleName() ];
		$titles = [];
		$fits = true;
		$i = 0;
		// TODO: Consider grouping the data by "type" so on the client-side one could
		// access result.data.copyedit rather an iterating over everything.
		'@phan-var TaskSet $tasks';
		foreach ( $tasks as $i => $task ) {
			$title = Title::newFromLinkTarget( $task->getTitle() );
			$extraData = [
				'tasktype' => $task->getTaskType()->getId(),
				'difficulty' => $task->getTaskType()->getDifficulty(),
				'order' => $i,
				'qualityGateIds' => $task->getTaskType()->getQualityGateIds(),
				'qualityGateConfig' => $tasks->getQualityGateConfig(),
				'token' => $task->getToken()
			];
			if ( $task->getTopics() ) {
				foreach ( $task->getTopicScores() as $id => $score ) {
					// Handling associative arrays is annoying in JS; return the data as
					// a list of (topic ID, score) pairs instead.
					$extraData['topics'][] = [ $id, $score ];
				}
			}

			if ( $resultPageSet ) {
				$titles[] = $title;
				$resultPageSet->setGeneratorData( $title, $extraData );
			} else {
				$fits = $result->addValue( array_merge( $basePath, [ 'suggestions' ] ), null, [
					'title' => $title->getPrefixedText(),
				] + $extraData );
				if ( !$fits ) {
					// Could not add to ApiResult due to hitting response size limits.
					break;
				}
			}
		}
		// If we aborted because of $fits, $i is the 0-based index (relative to $offset) of which
		// item we need to continue with in the next request, so we need to start with $offset + $i.
		// If we finished (reached $limit) then $i points to the last task we successfully added.
		if ( !$fits || $tasks->getTotalCount() > $offset + $i + 1 ) {
			// $i is 0-based and will point to the first record not added, so the offset must be one larger.
			$this->setContinueEnumParameter( 'offset', $offset + $i + (int)$fits );
		}

		if ( $resultPageSet ) {
			$resultPageSet->populateFromTitles( $titles );
			$result->addValue( $this->getModuleName(), 'totalCount', $tasks->getTotalCount() );
			$result->addValue( $this->getModuleName(), 'qualityGateConfig', $tasks->getQualityGateConfig() );
			if ( $debug ) {
				$result->addValue( $this->getModuleName(), 'debug', $tasks->getDebugData() );
			}
		} else {
			$result->addValue( $basePath, 'totalCount', $tasks->getTotalCount() );
			$result->addValue( $basePath, 'qualityGateConfig', $tasks->getQualityGateConfig() );
			$result->addIndexedTagName( array_merge( $basePath, [ 'suggestions' ] ), 'suggestion' );
			if ( $debug ) {
				$result->addValue( $basePath, 'debug', $tasks->getDebugData() );
			}
		}

		// TODO: EventLogging?
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		$taskTypes = $this->configurationLoader->getTaskTypes();
		$topics = $this->getTopics();
		// Ensure valid values, tasks/topics might be empty during tests.
		$taskLimit = max( count( $taskTypes ), 1 );
		$topicsLimit = max( count( $topics ), 1 );

		return [
			'tasktypes' => [
				ParamValidator::PARAM_TYPE => array_keys( $taskTypes ),
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => $taskLimit,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => $taskLimit,
				ParamValidator::PARAM_DEFAULT => [],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => array_map( function ( TaskType $taskType ) {
					return $taskType->getName( $this->getContext() );
				}, $taskTypes ),
			],
			'topics' => [
				ParamValidator::PARAM_TYPE => array_keys( $topics ),
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => $topicsLimit,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => $topicsLimit,
				ParamValidator::PARAM_DEFAULT => [],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => array_map( function ( Topic $topic ) {
					return $topic->getName( $this->getContext() );
				}, $topics ),
			],
			 'topicsmode' => [
				ParamValidator::PARAM_TYPE => SearchStrategy::TOPIC_MATCH_MODES,
			],
			'limit' => [
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MAX => 250,
				IntegerDef::PARAM_MAX2 => 250,
			],
			'offset' => [
				ParamValidator::PARAM_TYPE => 'integer',
				IntegerDef::PARAM_MIN => 1,
				ApiBase::PARAM_RANGE_ENFORCE => true,
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'debug' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'excludepageids' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => 1000,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => 1000,
			]
		];
	}

	/**
	 * @return Topic[] Array of topic id => topic
	 */
	protected function getTopics() {
		$topics = $this->configurationLoader->loadTopics();
		if ( $topics instanceof StatusValue ) {
			return [];
		}
		return array_combine( array_map( static function ( Topic $topic ) {
			return $topic->getId();
		}, $topics ), $topics ) ?: [];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		$p = $this->getModulePrefix();
		return [
			"action=query&list=growthtasks&{$p}tasktypes=copyedit" => 'apihelp-query+growthtasks-example-1',
			"action=query&generator=growthtasks&g{$p}limit=max&prop=info|revision"
				=> 'apihelp-query+growthtasks-example-2',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:GrowthExperiments#API';
	}

}
