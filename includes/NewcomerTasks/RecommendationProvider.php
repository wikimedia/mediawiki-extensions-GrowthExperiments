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
	 * don't have recommendations at all.
	 *
	 * @param LinkTarget $title
	 * @param TaskType $taskType This must be the task type matching the recommendation provider.
	 * @return Recommendation|StatusValue The recommendation, or a StatusValue on error.
	 *   The StatusValue's OK flag will determine whether this is an unexpected error that
	 *   should be sent to the production error logs, or something that can happen under normal
	 *   circumstances (e.g. the given article simply not having any recommendations).
	 */
	public function get( LinkTarget $title, TaskType $taskType );

}
