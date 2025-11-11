<?php

namespace GrowthExperiments\NewcomerTasks\ReviseTone;

use GrowthExperiments\NewcomerTasks\SubpageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Title\Title;
use StatusValue;

/**
 * Recommendation provider for tone check suggestions stored as
 * JSON subpages in the MediaWiki namespace.
 */
class SubpageReviseToneRecommendationProvider extends SubpageRecommendationProvider {

	/** @inheritDoc */
	protected static $subpageName = 'tone';

	/** @inheritDoc */
	protected static $serviceName = 'GrowthExperimentsReviseToneRecommendationProvider';

	/** @inheritDoc */
	protected static $recommendationTaskTypeClass = ReviseToneTaskType::class;

	/**
	 * @inheritDoc
	 * @return ReviseToneRecommendation|StatusValue
	 */
	public function createRecommendation(
		Title $title,
		TaskType $taskType,
		array $data,
		array $suggestionFilters = []
	): ReviseToneRecommendation|StatusValue {
		return new ReviseToneRecommendation( $title, $data['text'] );
	}

}
