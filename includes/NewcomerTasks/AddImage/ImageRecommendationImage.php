<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use JsonSerializable;
use MediaWiki\Linker\LinkTarget;
use TitleValue;

/**
 * Represents an individual suggested image within an ImageRecommendation.
 */
class ImageRecommendationImage implements JsonSerializable {

	/**
	 * The recommendation is based on the image property (P18 or similar) of the
	 * linked Wikidata item.
	 */
	public const SOURCE_WIKIDATA = 'wikidata';
	/**
	 * The recommendation is based on images used in other language versions of the article.
	 */
	public const SOURCE_WIKIPEDIA = 'wikipedia';
	/**
	 * The recommendation is based on the Wikimedia Commons category referenced in the
	 * linked Wikidata item (via the P373 property).
	 */
	public const SOURCE_COMMONS = 'commons';

	/** @var LinkTarget */
	private $imageTitle;

	/** @var string */
	private $source;

	/** @var string[] */
	private $projects;

	/** @var array */
	private $metadata;

	/**
	 * Create an ImageRecommendationImage object from an array representation.
	 * This is the inverse of toArray().
	 * @param array $imageData
	 * @return ImageRecommendationImage
	 */
	public static function fromArray( array $imageData ): ImageRecommendationImage {
		return new ImageRecommendationImage(
			new TitleValue( NS_FILE, $imageData['image'] ),
			$imageData['source'],
			$imageData['projects'] ?? [],
			$imageData['metadata'] ?? []
		);
	}

	/**
	 * @param LinkTarget $imageTitle The recommended image.
	 * @param string $source One of the SOURCE_* constants.
	 * @param string[] $projects List of projects (as wiki IDs) the recommendation was based on.
	 *   Only for SOURCE_INTERWIKI.
	 * @param array $metadata Metadata for the recommended image.
	 */
	public function __construct(
		LinkTarget $imageTitle,
		string $source,
		array $projects = [],
		array $metadata = []
	) {
		$this->imageTitle = $imageTitle;
		$this->source = $source;
		$this->projects = $projects;
		$this->metadata = $metadata;
	}

	/**
	 * Get the recommended image.
	 * @return LinkTarget
	 */
	public function getImageTitle(): LinkTarget {
		return $this->imageTitle;
	}

	/**
	 * Get the information source the recommendation was based on.
	 * @return string One of the SOURCE_* constants.
	 */
	public function getSource(): string {
		return $this->source;
	}

	/**
	 * Get the list of projects the recommendation was based on. This only makes sense when
	 * getSource() is SOURCE_INTERWIKI, and will be an empty array otherwise.
	 * @return string[] List of wiki IDs.
	 */
	public function getProjects(): array {
		return $this->projects;
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		$filename = $this->imageTitle->getDBkey();
		return [
			'image' => $filename,
			'displayFilename' => str_replace( '_', ' ', $filename ),
			'source' => $this->source,
			'projects' => $this->projects,
			'metadata' => $this->metadata,
		];
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
