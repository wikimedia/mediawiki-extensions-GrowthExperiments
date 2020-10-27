<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use MediaWiki\Linker\LinkTarget;

/**
 * Value object for machine-generated link recommendations.
 */
class LinkRecommendation {

	/** @var LinkTarget */
	private $title;

	/** @var int */
	private $pageId;

	/** @var int */
	private $revisionId;

	/** @var array[] */
	private $links;

	/**
	 * @param LinkTarget $title Page for which the recommendations were generated.
	 * @param int $pageId Page for which the recommendations were generated.
	 * @param int $revisionId Revision ID for which the recommendations were generated.
	 * @param array[] $data Other state data, as provided by toArray().
	 */
	public function __construct( LinkTarget $title, int $pageId, int $revisionId, array $data ) {
		$this->title = $title;
		$this->pageId = $pageId;
		$this->revisionId = $revisionId;
		$this->links = $data['links'] ?? [];
	}

	/**
	 * Get the title of the page for which the recommendations were generated.
	 * @return LinkTarget
	 */
	public function getTitle(): LinkTarget {
		return $this->title;
	}

	/**
	 * Get the ID of the page for which the recommendations were generated.
	 * @return int
	 */
	public function getPageId(): int {
		return $this->pageId;
	}

	/**
	 * Get the revision ID for which the recommendations were generated.
	 * @return int
	 */
	public function getRevisionId(): int {
		return $this->revisionId;
	}

	/**
	 * JSON-ifiable data that represents all state of the object except the page identity and
	 * revision.
	 * @return array[] An array with the following fields:
	 *   - links: Link recommendation data. A list of associative arrays with:
	 *     - target (string): the title the recommended link points to, in any format that
	 *       can be parsed by TitleParser.
	 *     - text (string): text of the recommended link. This text is present and unlinked in
	 *       the article revision that was used for generating recommendations.
	 *     - index (integer): 0-based index of the link, within all occurrences of the link text.
	 */
	public function toArray(): array {
		return [ 'links' => $this->links ];
	}

}
