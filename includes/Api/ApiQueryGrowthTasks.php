<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiPageSet;
use ApiQuery;
use ApiQueryGeneratorBase;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TemplateBasedTask;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use Title;

/**
 * API endpoint for Newcomer Tasks feature.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks
 */
class ApiQueryGrowthTasks extends ApiQueryGeneratorBase {

	/** @var TaskSuggester */
	private $taskSuggester;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param TaskSuggester $taskSuggester
	 */
	public function __construct(
		ApiQuery $queryModule,
		$moduleName,
		TaskSuggester $taskSuggester
	) {
		parent::__construct( $queryModule, $moduleName, 'gt' );
		$this->taskSuggester = $taskSuggester;
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
		if ( $user->isAnon() ) {
			$this->dieWithError( [ 'apierror-mustbeloggedin-generic' ] );
		}
		$params = $this->extractRequestParams();
		$taskTypes = $params['tasktypes'];
		$topics = $params['topics'];
		$limit = $params['limit'];
		$offset = $params['offset'];
		$debug = $params['debug'];

		/** @var TaskSet $tasks */
		$tasks = $this->taskSuggester->suggest( $user, $taskTypes, $topics, $limit, $offset, $debug );
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
				$extraData['topics'] = array_map( function ( Topic $topic ) {
					return $topic->getId();
				}, $task->getTopics() );
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
		return [
			'tasktypes' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			'topics' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
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
			],
		];
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
