<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\TaskType\StructuredTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;

/**
 * A shared interface for providing recommendations for articles.
 * @see StructuredTaskTypeHandler::getRecommendationProvider()
 */
interface RecommendationProvider {

	/**
	 * Get a recommendation (or an error message) for a given article.
	 * Recommendations are typically serialized and sent to the frontend logic which converts them
	 * to a wikitext or Parsoid HTML change.
	 * Recommendations are not guaranteed to exist for all pages; typically you should only try to
	 * fetch one for pages returned by TaskSuggester for the appropriate task type. Some task types
	 * don't have recmmendations at all.
	 * A warning status should be returned when the title had no recommendations, and a fatal
	 * status when there was some unexpected error.
	 * @param LinkTarget $title
	 * @param TaskType $taskType This must be the task type matching the recommendation provider.
	 * @return Recommendation|StatusValue
	 */
	public function get( LinkTarget $title, TaskType $taskType );

}
