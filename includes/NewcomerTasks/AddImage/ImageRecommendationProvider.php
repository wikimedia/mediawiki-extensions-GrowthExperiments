<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;

/** @inheritDoc */
interface ImageRecommendationProvider extends RecommendationProvider {
	// This is identical to the parent class, it just exists as a placeholder for the type hint.

	/**
	 * @inheritDoc
	 * @param ImageRecommendationTaskType $taskType
	 * @phan-param TaskType $taskType
	 * @return ImageRecommendation|StatusValue
	 */
	public function get( LinkTarget $title, TaskType $taskType );

}
