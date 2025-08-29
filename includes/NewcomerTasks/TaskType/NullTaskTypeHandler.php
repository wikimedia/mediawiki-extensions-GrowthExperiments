<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use InvalidArgumentException;
use LogicException;
use StatusValue;

/**
 * A "fake" task type handler for searching for task type candidates. Since it will return an
 * empty search term, the TaskSuggester will return a set of "tasks" which meet all the
 * non-tasktype-specific requirements and can be used for generating new tasks (for task types
 * where that kind of thing makes sense).
 */
class NullTaskTypeHandler extends TaskTypeHandler {

	/**
	 * Get a null task type. This task type should only be used for searching,
	 * with the $useCache flag off.
	 * @param string $id Task ID.
	 * @param string $extraSearchConditions Extra conditions to append to the search query.
	 * @return TaskType
	 */
	public static function getNullTaskType(
		string $id,
		string $extraSearchConditions = ''
	): TaskType {
		$taskType = new NullTaskType( $id, $extraSearchConditions );
		$taskType->setHandlerId( 'null' );
		return $taskType;
	}

	public function __construct() {
		// parent() call omitted intentionally.
	}

	/** @inheritDoc */
	public function getId(): string {
		return 'null';
	}

	/** @inheritDoc */
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		return StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-nulltasktype' );
	}

	/** @inheritDoc */
	public function validateTaskTypeObject( TaskType $taskType ): StatusValue {
		return StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-nulltasktype' );
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( !$taskType instanceof NullTaskType ) {
			throw new InvalidArgumentException( 'Invalid task type' );
		}
		return $taskType->getExtraSearchConditions();
	}

	/** @inheritDoc */
	public function getChangeTags( ?string $taskType = null ): array {
		return [];
	}

	/**
	 * @inheritDoc
	 * @suppress PhanPluginNeverReturnMethod LSP/ISP violation
	 */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		throw new LogicException( 'This should never be called' );
	}

	/** @inheritDoc */
	public function getSubmissionHandler(): SubmissionHandler {
		return new NullSubmissionHandler();
	}

	public function getTaskTypeIdByChangeTagName( string $changeTagName ): ?string {
		return null;
	}
}
