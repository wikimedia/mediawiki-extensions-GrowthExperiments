<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use MediaWiki\User\UserIdentityValue;
use Status;
use StatusValue;

class SuggestionsInfo {
	/**
	 * @var ConfigurationLoader
	 */
	private $configurationLoader;
	/**
	 * @var TaskTypeHandlerRegistry
	 */
	private $taskTypeHandlerRegistry;
	/**
	 * @var TaskSuggesterFactory
	 */
	private $taskSuggesterFactory;

	/**
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 */
	public function __construct(
		TaskSuggesterFactory $taskSuggesterFactory,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader
	) {
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->configurationLoader = $configurationLoader;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
	}

	/**
	 * Get information about available newcomer tasks segmented by task and topic.
	 *
	 * @return array
	 */
	public function getInfo(): array {
		$user = new UserIdentityValue( 0, 'SuggestionsInfo' );
		$taskSuggester = $this->taskSuggesterFactory->create();
		$taskTypes = $this->configurationLoader->loadTaskTypes();
		$topics = $this->configurationLoader->loadTopics();
		$data = [];
		if ( $taskTypes instanceof StatusValue ) {
			$data['error']['taskTypes'] = Status::wrap( $taskTypes )->getWikiText();
		}
		if ( $topics instanceof StatusValue ) {
			$data['error']['topics'] = Status::wrap( $topics )->getWikiText();
		}
		if ( $taskTypes instanceof StatusValue || $topics instanceof StatusValue ) {
			return $data;
		}
		$totalCount = 0;
		foreach ( $taskTypes as $taskType ) {
			$data['tasks'][$taskType->getId()]['totalCount'] = 0;
			$data['tasks'][$taskType->getId()]['search'] = $this->taskTypeHandlerRegistry
				->getByTaskType( $taskType )
				->getSearchTerm( $taskType );
			$result = $taskSuggester->suggest(
				$user,
				[ $taskType->getId() ],
				[],
				1,
				null,
				[ 'useCache' => false ]
			);
			if ( $result instanceof StatusValue ) {
				// Use -1 as a crude indicator of an error.
				$data['tasks'][$taskType->getId()]['totalCount'] = -1;
				continue;
			}
			$data['tasks'][$taskType->getId()]['totalCount'] += $result->getTotalCount();
			$totalCount += $result->getTotalCount();
		}
		foreach ( $topics as $topic ) {
			$data['topics'][$topic->getId()]['totalCount'] = 0;
			foreach ( $taskTypes as $taskType ) {
				$result = $taskSuggester->suggest(
					$user,
					[ $taskType->getId() ],
					[ $topic->getId() ],
					1,
					null,
					[ 'useCache' => false ]
				);
				if ( $result instanceof StatusValue ) {
					// Use -1 as a crude indicator of an error.
					$data['topics'][$topic->getId()]['tasks'][$taskType->getId()]['count'] = -1;
					continue;
				}
				$data['topics'][$topic->getId()]['tasks'][$taskType->getId()]['count'] =
					$result->getTotalCount();
				$data['topics'][$topic->getId()]['totalCount'] += $result->getTotalCount();
			}
		}
		$data['totalCount'] = $totalCount;
		return $data;
	}
}
