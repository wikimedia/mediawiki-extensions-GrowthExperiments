<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

/**
 * This is not a true recommendation and intentionally does not extend the {@see Recommendation} interface
 *
 * If this class appears where a generic Recommendation is expected, then a serious error has occurred somewhere.
 * Thus, it is defined as a separate class to catch these errors early through PHP's type-checking.
 */
class NullLinkRecommendation {

	private int $pageId;
	private int $revisionId;

	public function __construct(
		int $pageId,
		int $revisionId
	) {
		$this->pageId = $pageId;
		$this->revisionId = $revisionId;
	}

	/**
	 * Get the ID of the page for which the recommendations were generated.
	 */
	public function getPageId(): int {
		return $this->pageId;
	}

	/**
	 * Get the revision ID for which the recommendations were generated.
	 */
	public function getRevisionId(): int {
		return $this->revisionId;
	}
}
