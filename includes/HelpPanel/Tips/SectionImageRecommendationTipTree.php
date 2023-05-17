<?php

namespace GrowthExperiments\HelpPanel\Tips;

use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;

class SectionImageRecommendationTipTree extends ImageRecommendationTipTree {

	/** @inheritDoc */
	protected function getTaskTypeId(): string {
		return SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
	}
}
