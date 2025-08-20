<?php
namespace GrowthExperiments\NewcomerTasks\TaskType;

class ImproveToneTaskType extends TaskType {

	/** @inheritDoc */
	protected const IS_MACHINE_SUGGESTION = true;

	/** @inheritDoc */
	public function __construct(
		$id,
		$difficulty,
		array $extraData = [],
		array $excludedTemplates = [],
		array $excludedCategories = []
	) {
		parent::__construct( $id, $difficulty, $extraData, $excludedTemplates, $excludedCategories );
	}
}
