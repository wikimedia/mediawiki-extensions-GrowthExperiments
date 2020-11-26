<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use SearchResult;
use StatusValue;

/**
 * A TaskTypeHandler is responsible for all the type-specific behavior of some TaskType
 * (or group of TaskTypes) such as constructing the TaskType object from configuration or
 * constructing the search query that corresponds to the TaskType.
 *
 * TaskTypeHandlers are identified by their can be obtained from the TaskTypeRegis
 */
abstract class TaskTypeHandler {

	/**
	 * Change tag used to track edits made via suggested edit tasks. Subbtasks might add
	 * or replace with more specific tags.
	 */
	public const NEWCOMER_TASK_TAG = 'newcomer task';

	/** @var ConfigurationValidator */
	protected $configurationValidator;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 */
	public function __construct( ConfigurationValidator $configurationValidator ) {
		$this->configurationValidator = $configurationValidator;
	}

	/**
	 * Get the handler ID of this handler.
	 * This is mainly for internal use by TaskTypeHandlerRegistry.
	 * @return string
	 */
	abstract public function getId(): string;

	/**
	 * Validate task configuration used by ConfigurationLoader.
	 * @param string $taskTypeId
	 * @param array $config
	 * @return StatusValue
	 * @see ::validateTaskTypeObject
	 */
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		$status = $this->configurationValidator->validateIdentifier( $taskTypeId );

		$groupFieldStatus = $this->configurationValidator->validateRequiredField( 'group',
			$config, $taskTypeId );
		$status->merge( $groupFieldStatus );
		if ( $groupFieldStatus->isOK() &&
			 !in_array( $config['group'], TaskType::DIFFICULTY_CLASSES, true )
		) {
			$status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidgroup',
				$config['group'], $taskTypeId );
		}

		return $status;
	}

	/**
	 * Validate a task object. This is a companion to validateTaskTypeConfiguration() - some
	 * validation requires a TaskType object (typically checking whether messages exist) but
	 * first we need to make sure the configuration is valid enough to create the object,
	 * and the two cannot be done in the same method due to inheritance.
	 * @param TaskType $taskType
	 * @return StatusValue
	 */
	public function validateTaskTypeObject( TaskType $taskType ): StatusValue {
		return $this->configurationValidator->validateTaskMessages( $taskType );
	}

	/**
	 * @param string $taskTypeId
	 * @param array $config Task type configuration. Caller is assumed to have checked it
	 *   with validateTaskTypeConfiguration().
	 * @return TaskType
	 * @note Subclasses overriding this method must set the handled ID of the task type.
	 */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		$extraData = [ 'learnMoreLink' => $config['learnmore'] ?? null ];
		$taskType = new TaskType( $taskTypeId, $config['group'], $extraData );
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/**
	 * Get a CirrusSearch search term corresponding to this task.
	 * @param TaskType $taskType
	 * @return string
	 */
	abstract public function getSearchTerm( TaskType $taskType ): string;

	/**
	 * @param SearchQuery $query
	 * @param SearchResult $match
	 * @return Task
	 */
	public function createTaskFromSearchResult( SearchQuery $query, SearchResult $match ): Task {
		$taskType = $query->getTaskType();
		$topic = $query->getTopic();
		$task = new Task( $taskType, $match->getTitle() );
		if ( $topic ) {
			$score = 0;
			// CirrusSearch and our custom FauxSearchResultWithScore have this.
			if ( method_exists( $match, 'getScore' ) ) {
				// @phan-suppress-next-line PhanUndeclaredMethod
				$score = $match->getScore();
			}
			$task->setTopics( [ $topic ], [ $topic->getId() => $score ] );
		}

		return $task;
	}

	/**
	 * Get the list of change tags to apply to edits originating from this task type.
	 * @return string[]
	 */
	public function getChangeTags(): array {
		return [ self::NEWCOMER_TASK_TAG ];
	}

}
