<?php

namespace GrowthExperiments\NewcomerTasks;

use MediaWiki\Linker\LinkTarget;

/**
 * Shared base interface for recommendations for structured tasks.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Structured_tasks
 * @see RecommendationProvider
 */
interface Recommendation {

	/**
	 * Get the title of the page for which the recommendation was generated.
	 * @return LinkTarget
	 */
	public function getTitle(): LinkTarget;

	/**
	 * Serialize the object into an array representation that can be turned into JSON
	 * and passed to the frontend code.
	 * @return array
	 */
	public function toArray(): array;

}
