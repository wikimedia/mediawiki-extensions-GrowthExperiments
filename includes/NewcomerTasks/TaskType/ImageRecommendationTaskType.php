<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

class ImageRecommendationTaskType extends TaskType {

	/** @var bool */
	protected $isMachineSuggestion = true;

	/** @inheritDoc */
	public function shouldOpenInEditMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function getDefaultEditSection(): string {
		return 'all';
	}

}
