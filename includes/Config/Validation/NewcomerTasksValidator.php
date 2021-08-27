<?php

namespace GrowthExperiments\Config\Validation;

use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use StatusValue;

class NewcomerTasksValidator implements IConfigValidator {
	/** @var string[] */
	public const SUGGESTED_EDITS_TASK_TYPES = [
		'copyedit' => 'easy',
		'links' => 'easy',
		'references' => 'medium',
		'update' => 'medium',
		'expand' => 'hard'
	];

	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 */
	public function __construct(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	) {
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
	}

	/**
	 * @inheritDoc
	 */
	public function validate( array $config ): StatusValue {
		$status = StatusValue::newGood();

		// Code inspired by PageConfigurationLoader::parseTaskTypesFromConfig
		foreach ( $config as $taskId => $taskConfig ) {
			// Fall back to legacy task type handler if none specified
			$handlerId = $taskConfig['type'] ?? TemplateBasedTaskTypeHandler::ID;

			if ( !$this->taskTypeHandlerRegistry->has( $handlerId ) ) {
				$status->fatal(
					'growthexperiments-config-validator-newcomertasks-invalid-task-type-handler-id',
					$handlerId
				);
				continue;
			}

			$status->merge(
				$this->taskTypeHandlerRegistry->get( $handlerId )
					->validateTaskTypeConfiguration(
						$taskId,
						$taskConfig
					)
			);
		}
		return $status;
	}

	/**
	 * @inheritDoc
	 */
	public function validateVariable( string $variable, $value ): void {
		// Implemented as no-op, because this method throws an exception
		// which is not user friendly.
	}
}
