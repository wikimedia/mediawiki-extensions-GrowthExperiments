<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\Recommendation;
use MediaWiki\Linker\LinkTarget;

/**
 * Value object for machine-generated link recommendations. A link recommendation is a set of
 * suggested LinkRecommendationLinks for a given wiki page.
 */
class LinkRecommendation implements Recommendation {

	/**
	 * Fallback timestamp for tasks which have been stored before the code was updated to
	 * record a timestamp. It is 2000-01-01 (chosen arbitrarily as an "old" value).
	 * @internal Exposed for tests, should be treated as private.
	 */
	public const FALLBACK_TASK_TIMESTAMP = 946713600;

	/** @var LinkTarget */
	private $title;

	/** @var int */
	private $pageId;

	/** @var int */
	private $revisionId;

	/** @var LinkRecommendationLink[] */
	private $links;

	/** @var LinkRecommendationMetadata */
	private $metadata;

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
	 * Construct a LinkRecommendationMetadata value object with metadata included by the service for a
	 * link recommendation.
	 *
	 * Includes backward compatibility as this method is called when retrieving stored link recommendations
	 * in the cache, which may not have the meta field stored.
	 *
	 * @param array $meta
	 * @return LinkRecommendationMetadata
	 */
	public static function getMetadataFromArray( array $meta ): LinkRecommendationMetadata {
		return new LinkRecommendationMetadata(
			$meta['application_version'] ?? '',
			$meta['format_version'] ?? 1,
			$meta['dataset_checksums'] ?? [],
			$meta['task_timestamp'] ?? self::FALLBACK_TASK_TIMESTAMP
		);
	}

	/**
	 * @param LinkTarget $title Page for which the recommendations were generated.
	 * @param int $pageId Page for which the recommendations were generated.
	 * @param int $revisionId Revision ID for which the recommendations were generated.
	 * @param LinkRecommendationLink[] $links List of the recommended links
	 * @param LinkRecommendationMetadata $metadata Metadata associated with the links.
	 */
	public function __construct(
		LinkTarget $title,
		int $pageId,
		int $revisionId,
		array $links,
		LinkRecommendationMetadata $metadata
	) {
		$this->title = $title;
		$this->pageId = $pageId;
		$this->revisionId = $revisionId;
		$this->links = array_values( $links );
		$this->metadata = $metadata;
	}

	/** @inheritDoc */
	public function getTitle(): LinkTarget {
		return $this->title;
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

	/**
	 * Get the links recommended for the article.
	 * @return LinkRecommendationLink[]
	 */
	public function getLinks(): array {
		return $this->links;
	}

	public function getMetadata(): LinkRecommendationMetadata {
		return $this->metadata;
	}

	/**
	 * JSON-ifiable data that represents the state of the object except the page identity and
	 * revision.
	 * @return array[]
	 */
	public function toArray(): array {
		return [ 'links' => array_map( static function ( LinkRecommendationLink $linkRecommendationItem ) {
			return $linkRecommendationItem->toArray();
		}, $this->links ), 'meta' => $this->metadata->toArray() ];
	}

}
