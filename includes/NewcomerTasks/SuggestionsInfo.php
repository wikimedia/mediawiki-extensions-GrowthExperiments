<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserIdentityValue;
use StatusValue;

class SuggestionsInfo implements NewcomerTasksInfo {
	public const USER = 'SuggestionsInfo';

	public function __construct(
		private StatusFormatter $statusFormatter,
		private TaskSuggesterFactory $taskSuggesterFactory,
		private TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		private ConfigurationLoader $configurationLoader,
		private ITopicRegistry $topicRegistry
	) {
	}

	/** @inheritDoc */
	public function getInfo( array $options = [] ): array {
		$user = new UserIdentityValue( 0, 'SuggestionsInfo' );
		$taskSuggester = $this->taskSuggesterFactory->create( $this->configurationLoader );
		$taskTypes = $this->configurationLoader->loadTaskTypes();
		$topics = $this->topicRegistry->getTopics();
		$data = [];
		if ( $taskTypes instanceof StatusValue ) {
			$data['error']['taskTypes'] = $this->statusFormatter->getWikiText( $taskTypes );
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
				new TaskSetFilters(
					[ $taskType->getId() ],
					[]
				),
				0,
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
					new TaskSetFilters(
						[ $taskType->getId() ],
						[ $topic->getId() ]
					),
					0,
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
