<?php

namespace GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater;

use MediaWiki\Revision\RevisionRecord;
use StatusValue;

/**
 * Updates the search index when a new recommendation is found to indicate that the given
 * page has recommendations.
 * (Updates after a recommendation is accepted happen via the SearchDataForIndex hook,
 * while updates after a rejection happen via CirrusSearch::resetWeightedTags().)
 */
interface SearchIndexUpdater {

	/**
	 * Updates the search index when a new recommendation is found to indicate that the given
	 * page has recommendations.
	 * @param RevisionRecord $revisionRecord
	 * @return StatusValue Success status. This is a best effort; depending on the implementation,
	 *   failure might not be detectable.
	 */
	public function update( RevisionRecord $revisionRecord );

}
