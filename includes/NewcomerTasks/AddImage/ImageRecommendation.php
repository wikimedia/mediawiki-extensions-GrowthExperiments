<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\Recommendation;
use JsonSerializable;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleValue;

/**
 * Value object for machine-generated image recommendations. An image recommendation consists
 * of a set of suggested ImageRecommendationImages for a given wiki page.
 */
class ImageRecommendation implements Recommendation, JsonSerializable {

	/** @var LinkTarget */
	private $title;

	/** @var array */
	private $images;

	/** @var string */
	private $datasetId;

	/**
	 * Create an ImageRecommendation object from an array representation.
	 * This is the inverse of toArray().
	 * @param array $recommendationData
	 * @return self
	 */
	public static function fromArray( array $recommendationData ): ImageRecommendation {
		return new ImageRecommendation(
			new TitleValue( $recommendationData['titleNamespace'], $recommendationData['titleText'] ),
			array_map( [ ImageRecommendationImage::class, 'fromArray' ], $recommendationData['images'] ),
			$recommendationData['datasetId']
		);
	}

	/**
	 * @param LinkTarget $title Page for which the recommendations were generated.
	 * @param ImageRecommendationImage[] $images List of recommended images.
	 * @param string $datasetId Version of the recommendations dataset.
	 */
	public function __construct(
		LinkTarget $title,
		array $images,
		string $datasetId
	) {
		$this->title = $title;
		$this->images = $images;
		$this->datasetId = $datasetId;
	}

	/** @inheritDoc */
	public function getTitle(): LinkTarget {
		return $this->title;
	}

	/**
	 * Get the images recommended for the article.
	 * @return ImageRecommendationImage[]
	 */
	public function getImages(): array {
		return $this->images;
	}

	/**
	 * Get the version of the recommendations dataset.
	 */
	public function getDatasetId(): string {
		return $this->datasetId;
	}

	/**
	 * JSON-ifiable data that represents the state of the object.
	 * @return mixed[]
	 */
	public function toArray(): array {
		return [
			'titleNamespace' => $this->title->getNamespace(),
			'titleText' => $this->title->getText(),
			'images' => array_map( static function ( ImageRecommendationImage $image ) {
				return $image->toArray();
			}, $this->images ),
			'datasetId' => $this->datasetId,
		];
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
