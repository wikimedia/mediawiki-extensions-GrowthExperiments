<?php

namespace GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater;

use CirrusSearch\CirrusSearch;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionRecord;
use StatusValue;

/**
 * Update the search index via a direct Cirrus call. This is meant for development setups;
 * in production such updates can be aggregated and batched to reduce the number of index writes.
 */
class CirrusSearchIndexUpdater implements SearchIndexUpdater {

	/** @inheritDoc */
	public function update( RevisionRecord $revisionRecord ) {
		$cirrusSearch = new CirrusSearch();
		$pageIdentity = new PageIdentityValue(
			$revisionRecord->getId( $revisionRecord->getWikiId() ),
			$revisionRecord->getPage()->getNamespace(),
			$revisionRecord->getPage()->getDBkey(),
			$revisionRecord->getWikiId()
		);
		$cirrusSearch->updateWeightedTags( $pageIdentity,
			LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX );
		return StatusValue::newGood();
	}

}
