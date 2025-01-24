<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

/**
 * Normalized image recommendation data returned from the image recommendation API
 */
class ImageRecommendationData {

	/** @var mixed */
	private $filename;

	/** @var mixed */
	private $source;

	/** @var mixed */
	private $projects;

	/** @var mixed */
	private $datasetId;

	/** @var mixed */
	private $sectionNumber;

	/** @var mixed */
	private $sectionTitle;

	/**
	 * @param mixed $filename Recommendation image file name
	 * @param mixed $source Reason for the image being suggested; should be either
	 *   ImageRecommendationImage::SOURCE_WIKIDATA, ImageRecommendation::SOURCE_WIKIPEDIA or
	 *   ImageRecommendationImage::SOURCE_COMMONS
	 * @param mixed $projects Projects in which the image is found; separated by comma
	 * @param mixed $datasetId Dataset ID for the recommendation
	 * @param mixed $sectionNumber Section number for which the image is recommended (1-based
	 *   index of the section within the second-level sections), or null for top-level
	 *   recommendations.
	 * @param mixed $sectionTitle Wikitext of the section title for which the image is
	 *   recommended, or null for top-level recommendations.
	 */
	public function __construct(
		$filename = null,
		$source = null,
		$projects = null,
		$datasetId = null,
		$sectionNumber = null,
		$sectionTitle = null
	) {
		$this->filename = $filename;
		$this->source = $source;
		$this->projects = $projects;
		$this->datasetId = $datasetId;
		$this->sectionNumber = $sectionNumber;
		$this->sectionTitle = $sectionTitle;
	}

	/**
	 * @return mixed
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * @return mixed
	 */
	public function getSource() {
		return $this->source;
	}

	/**
	 * @return mixed
	 */
	public function getProjects() {
		return $this->projects;
	}

	/**
	 * Get an array of projects in which the image recommendation can be found
	 */
	public function getFormattedProjects(): array {
		if ( $this->projects ) {
			return array_map( static function ( $project ) {
				return preg_replace( '/[^a-zA-Z0-9_-]/', '', $project );
			}, explode( ',', $this->projects ) );
		}
		return [];
	}

	/**
	 * @return mixed
	 */
	public function getDatasetId() {
		return $this->datasetId ?? null;
	}

	/**
	 * @return mixed
	 */
	public function getSectionNumber() {
		return $this->sectionNumber;
	}

	/**
	 * @return mixed
	 */
	public function getSectionTitle() {
		return $this->sectionTitle;
	}
}
