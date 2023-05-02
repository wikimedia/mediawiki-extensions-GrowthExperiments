<?php

namespace GrowthExperiments\Config\Validation;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use StatusValue;

class NewcomerTasksValidator implements IConfigValidator {
	/**
	 * Default task type data. Should usually be accessed via
	 * SpecialEditGrowthConfig::getDefaultDataForEnabledTaskTypes().
	 */
	public const SUGGESTED_EDITS_TASK_TYPES = [
		'copyedit' => [
			'difficulty' => 'easy',
			'handler-id' => TemplateBasedTaskTypeHandler::ID,
			'icon' => 'article',
		],
		'links' => [
			'difficulty' => 'easy',
			'handler-id' => TemplateBasedTaskTypeHandler::ID,
			'icon' => 'article',
		],
		'link-recommendation' => [
			'difficulty' => 'easy',
			'handler-id' => LinkRecommendationTaskTypeHandler::ID,
			'icon' => 'robot',
		],
		'image-recommendation' => [
			'difficulty' => 'medium',
			'handler-id' => ImageRecommendationTaskTypeHandler::ID,
			'icon' => 'robot',
		],
		'section-image-recommendation' => [
			'difficulty' => 'medium',
			'handler-id' => SectionImageRecommendationTaskTypeHandler::ID,
			'icon' => 'robot',
		],
		'references' => [
			'difficulty' => 'medium',
			'handler-id' => TemplateBasedTaskTypeHandler::ID,
			'icon' => 'article',
		],
		'update' => [
			'difficulty' => 'medium',
			'handler-id' => TemplateBasedTaskTypeHandler::ID,
			'icon' => 'article',
		],
		'expand' => [
			'difficulty' => 'hard',
			'handler-id' => TemplateBasedTaskTypeHandler::ID,
			'icon' => 'article',
		]
	];

	public const SUGGESTED_EDITS_MACHINE_SUGGESTIONS_TASK_TYPES = [
		'link-recommendation',
		'image-recommendation',
		'section-image-recommendation'
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
			$taskTypeHandler = $this->taskTypeHandlerRegistry->get( $handlerId );

			$status->merge(
				$taskTypeHandler->validateTaskTypeConfiguration( $taskId, $taskConfig )
			);
			if ( $status->isGood() ) {
				$taskType = $taskTypeHandler->createTaskType( $taskId, $taskConfig );
				$status->merge( $taskTypeHandler->validateTaskTypeObject( $taskType ) );
			}
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

	/**
	 * @inheritDoc
	 */
	public function getDefaultContent(): array {
		return [];
	}
}
