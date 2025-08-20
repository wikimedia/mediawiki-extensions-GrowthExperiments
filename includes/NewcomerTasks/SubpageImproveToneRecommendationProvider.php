<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\TaskType\ImproveToneTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Title\Title;
use StatusValue;

/**
 * Recommendation provider for tone check suggestions stored as
 * JSON subpages in the MediaWiki namespace.
 */
class SubpageImproveToneRecommendationProvider extends SubpageRecommendationProvider {

	/** @inheritDoc */
	protected static $subpageName = 'tone';

	/** @inheritDoc */
	protected static $serviceName = 'GrowthExperimentsImproveToneRecommendationProvider';

	/** @inheritDoc */
	protected static $recommendationTaskTypeClass = ImproveToneTaskType::class;

	/**
	 * @inheritDoc
	 * @return ImproveToneRecommendation|StatusValue
	 */
	public function createRecommendation(
		Title $title,
		TaskType $taskType,
		array $data,
		array $suggestionFilters = []
	): ImproveToneRecommendation|StatusValue {
		return new ImproveToneRecommendation( $title, $data );
	}

}
