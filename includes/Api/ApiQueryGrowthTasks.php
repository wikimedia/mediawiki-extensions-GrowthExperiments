<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiPageSet;
use ApiQuery;
use ApiQueryGeneratorBase;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TemplateBasedTask;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use Title;

/**
 * API endpoint for Newcomer Tasks feature.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks
 */
class ApiQueryGrowthTasks extends ApiQueryGeneratorBase {

	/** @var TaskSuggesterFactory */
	private $taskSuggesterFactory;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param ConfigurationLoader $configurationLoader
	 */
	public function __construct(
		ApiQuery $queryModule,
		$moduleName,
		TaskSuggesterFactory $taskSuggesterFactory,
		ConfigurationLoader $configurationLoader
	) {
		parent::__construct( $queryModule, $moduleName, 'gt' );
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->configurationLoader = $configurationLoader;
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
		$limit = $params['limit'];
		$offset = $params['offset'];
		$debug = $params['debug'];

		$taskSuggester = $this->taskSuggesterFactory->create();

		/** @var TaskSet $tasks */
		$tasks = $taskSuggester->suggest( $user, $taskTypes, $topics, $limit, $offset, $debug );
		if ( $tasks instanceof StatusValue ) {
			$this->dieStatus( $tasks );
		}

		$result = $this->getResult();
		$basePath = [ 'query', $this->getModuleName() ];
		$titles = [];
		$fits = true;
		$i = 0;
		// TODO: Consider grouping the data by "type" so on the client-side one could
		// access result.data.copyedit rather an iterating over everything.
		'@phan-var TaskSet $tasks';
		foreach ( $tasks as $i => $task ) {
			/** @var Task $task */
			$title = Title::newFromLinkTarget( $task->getTitle() );
			$extraData = [
				'tasktype' => $task->getTaskType()->getId(),
				'difficulty' => $task->getTaskType()->getDifficulty(),
				'order' => $i,
			];
			if ( $task->getTopics() ) {
				foreach ( $task->getTopicScores() as $id => $score ) {
					// Handling associative arrays is annoying in JS; return the data as
					// a list of (topic ID, score) pairs instead.
					$extraData['topics'][] = [ $id, $score ];
				}
			}
			if ( $task instanceof TemplateBasedTask && $task->getTemplates() ) {
				$extraData['maintenancetemplates'] = array_map( function ( LinkTarget $template ) {
					return $template->getText();
				}, $task->getTemplates() );
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
			$this->setContinueEnumParameter( 'offset', $offset + $i + $fits );
		}

		if ( $resultPageSet ) {
			$resultPageSet->populateFromTitles( $titles );
			$result->addValue( $this->getModuleName(), 'totalCount', $tasks->getTotalCount() );
			if ( $debug ) {
				$result->addValue( $this->getModuleName(), 'debug', $tasks->getDebugData() );
			}
		} else {
			$result->addValue( $basePath, 'totalCount', $tasks->getTotalCount() );
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

		return [
			'tasktypes' => [
				ApiBase::PARAM_TYPE => array_keys( $taskTypes ),
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => array_map( function ( TaskType $taskType ) {
					return $taskType->getName( $this->getContext() );
				}, $taskTypes ),
			],
			'topics' => [
				ApiBase::PARAM_TYPE => array_keys( $topics ),
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => array_map( function ( Topic $topic ) {
					return $topic->getName( $this->getContext() );
				}, $topics ),
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MAX => 250,
				ApiBase::PARAM_MAX2 => 250,
			],
			'offset' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_RANGE_ENFORCE => true,
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'debug' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
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
		return array_combine( array_map( function ( Topic $topic ) {
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
