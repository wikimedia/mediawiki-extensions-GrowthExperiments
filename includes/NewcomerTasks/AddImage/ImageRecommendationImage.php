<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use JsonSerializable;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleValue;

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
	/**
	 * The recommendation is based on the image property (P18 or similar) of a
	 * Wikidata item that has been guessed by analyzing the text of an article section.
	 */
	public const SOURCE_WIKIDATA_SECTION_TOPICS = 'wikidata-section-topics';
	/**
	 * The recommendation is based on another language version of the article using the
	 * image in the same section (where "same section" is being defined as the Wikidata
	 * items guessed by analyzing the text of the sections having significant overlap).
	 */
	public const SOURCE_WIKIDATA_SECTION_ALIGNMENT = 'wikidata-section-alignment';
	/**
	 * The recommendation is based on both section topics and section alignment.
	 */
	public const SOURCE_WIKIDATA_SECTION_INTERSECTION = 'wikidata-section-intersection';

	public const KNOWN_SOURCES = [
		self::SOURCE_WIKIDATA,
		self::SOURCE_WIKIPEDIA,
		self::SOURCE_COMMONS,
		self::SOURCE_WIKIDATA_SECTION_TOPICS,
		self::SOURCE_WIKIDATA_SECTION_ALIGNMENT,
		self::SOURCE_WIKIDATA_SECTION_INTERSECTION,
	];

	/**
	 * Maps deprecated source names to their current equivalents.
	 */
	public const SOURCE_ALIASES = [
		'wikidata-section' => self::SOURCE_WIKIDATA_SECTION_TOPICS,
	];

	private LinkTarget $imageTitle;
	private string $source;
	/** @var string[] */
	private array $projects;
	private array $metadata;
	private ?int $sectionNumber;
	private ?string $sectionTitle;

	/**
	 * Create an ImageRecommendationImage object from an array representation.
	 * This is the inverse of toArray().
	 */
	public static function fromArray( array $imageData ): self {
		return new self(
			new TitleValue( NS_FILE, $imageData['image'] ),
			$imageData['source'],
			$imageData['projects'] ?? [],
			$imageData['metadata'] ?? [],
			$imageData['sectionNumber'] ?? null,
			$imageData['sectionTitle'] ?? null
		);
	}

	/**
	 * @param LinkTarget $imageTitle The recommended image.
	 * @param string $source One of the SOURCE_* constants.
	 * @param string[] $projects List of projects (as wiki IDs) the recommendation was based on.
	 *   Only for SOURCE_INTERWIKI.
	 * @param array $metadata Metadata for the recommended image.
	 * @param int|null $sectionNumber Section number for which the image is recommended (1-based
	 *   index of the section within the second-level sections), or null for top-level
	 *   recommendations.
	 * @param string|null $sectionTitle Wikitext of the section title for which the image is
	 *   recommended, or null for top-level recommendations.
	 */
	public function __construct(
		LinkTarget $imageTitle,
		string $source,
		array $projects = [],
		array $metadata = [],
		?int $sectionNumber = null,
		?string $sectionTitle = null
	) {
		$this->imageTitle = $imageTitle;
		$this->source = $source;
		$this->projects = $projects;
		$this->metadata = $metadata;
		$this->sectionNumber = $sectionNumber;
		$this->sectionTitle = $sectionTitle;
	}

	/**
	 * Get the recommended image.
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
	 * Section number for which the image is recommended (1-based index of the section within
	 * the second-level sections), or null for top-level recommendations.
	 * @return int|null
	 */
	public function getSectionNumber(): ?int {
		return $this->sectionNumber;
	}

	/**
	 * Wikitext of the section title for which the image is recommended, or null for top-level
	 * recommendations.
	 * @return string|null
	 */
	public function getSectionTitle(): ?string {
		return $this->sectionTitle;
	}

	public function toArray(): array {
		$filename = $this->imageTitle->getDBkey();
		return [
			'image' => $filename,
			'displayFilename' => str_replace( '_', ' ', $filename ),
			'source' => $this->source,
			'projects' => $this->projects,
			'metadata' => $this->metadata,
			'sectionNumber' => $this->sectionNumber,
			'sectionTitle' => $this->sectionTitle,
		];
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
