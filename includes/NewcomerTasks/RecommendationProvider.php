<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;

/**
 * A shared interface for providing recommendations for articles.
 */
interface RecommendationProvider {

	/**
	 * Get a recommendation (or an error message) for a given article.
	 * A warning status should be returned when the title had no recommendations, and
	 * a fatal status when there was some unexpected error.
	 * @param LinkTarget $title
	 * @param TaskType $taskType
	 * @return Recommendation|StatusValue
	 */
	public function get( LinkTarget $title, TaskType $taskType );

}
