<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use MediaWiki\Linker\LinkTarget;

/**
 * Value object for machine-generated link recommendations. A link recommendation is a set of
 * suggested LinkRecommendationItems for a given wiki page.
 */
class LinkRecommendation {

	/** @var LinkTarget */
	private $title;

	/** @var int */
	private $pageId;

	/** @var int */
	private $revisionId;

	/** @var LinkRecommendationLink[] */
	private $links;

	/**
	 * Parse a JSON array into a LinkRecommendationLink array. This is more or less the inverse of
	 * toArray(), except it only returns a link list, not a LinkRecommendation.
	 * @param array $array
	 * @return LinkRecommendationLink[]
	 */
	public static function getLinksFromArray( array $array ): array {
		// FIXME this should probably live in some de/serializer class, with proper error handling.
		$links = [];
		foreach ( $array as $item ) {
			$links[] = new LinkRecommendationLink(
				$item['link_text'],
				$item['link_target'],
				$item['match_index'],
				$item['wikitext_offset'],
				$item['score'],
				$item['context_before'],
				$item['context_after'],
				$item['link_index']
			);
		}
		return $links;
	}

	/**
	 * @param LinkTarget $title Page for which the recommendations were generated.
	 * @param int $pageId Page for which the recommendations were generated.
	 * @param int $revisionId Revision ID for which the recommendations were generated.
	 * @param LinkRecommendationLink[] $links List of the recommended links
	 */
	public function __construct( LinkTarget $title, int $pageId, int $revisionId, array $links ) {
		$this->title = $title;
		$this->pageId = $pageId;
		$this->revisionId = $revisionId;
		$this->links = array_values( $links );
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
	 * @return LinkRecommendationLink[]
	 */
	public function getLinks(): array {
		return $this->links;
	}

	/**
	 * JSON-ifiable data that represents the state of the object except the page identity and
	 * revision.
	 * @return array[]
	 */
	public function toArray(): array {
		return [ 'links' => array_map( function ( LinkRecommendationLink $linkRecommendationItem ) {
			return $linkRecommendationItem->toArray();
		}, $this->links ) ];
	}

}
