<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use MediaWiki\Linker\LinkTarget;

/**
 * Value object for machine-generated link recommendations.
 */
class LinkRecommendation {

	/**
	 * Key for the link target in the recommendation data. This is the page the recommended
	 * link points to. The value is a page title in any format that can be parsed by TitleParser.
	 */
	public const FIELD_TARGET = 'target';
	/**
	 * Key for the text of the recommended link in the recommendation data. This text is present
	 * and unlinked in the article revision that was used for generating recommendations.
	 */
	public const FIELD_TEXT = 'text';
	/**
	 * Key for the link index in the recommendation data. This is a 0-based index of the link,
	 * within all occurrences of the link text.
	 */
	public const FIELD_INDEX = 'index';
	/**
	 * Key for the score  in the recommendation data. This is the confidence of the recommendation,
	 * a number between 0 and 1.
	 */
	public const FIELD_SCORE = 'score';

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
	 * Get the links recommended for the article.
	 * @return array[] A list of link recommendations, each an associative array with the
	 *   fields FIELD_TARGET, FIELD_TEXT, FIELD_INDEX, FIELD_SCORE.
	 * @see LinkRecommendation::FIELD_TARGET
	 * @see LinkRecommendation::FIELD_TEXT
	 * @see LinkRecommendation::FIELD_INDEX
	 * @see LinkRecommendation::FIELD_SCORE
	 */
	public function getLinks(): array {
		return $this->links;
	}

	/**
	 * JSON-ifiable data that represents all state of the object except the page identity and
	 * revision.
	 * @return array[] An array with the following fields:
	 *   - links: Link recommendation data, as returned by {@see :getLinks()}.
	 */
	public function toArray(): array {
		return [ 'links' => $this->links ];
	}

}
