<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

/**
 * Represents metadata for link recommendation set.
 */
class LinkRecommendationMetadata {

	/** @var string */
	private $applicationVersion;
	/** @var int */
	private $formatVersion;
	/** @var array */
	private $datasetChecksums;

	/**
	 * @param string $applicationVersion The git commit hash of the application that generated the recommendation.
	 * @param int $formatVersion The format version for the recommendation.
	 * @param array $datasetChecksums An array of datasets / checksums, keys are the dataset names and values are the
	 *   checksums
	 */
	public function __construct(
		string $applicationVersion,
		int $formatVersion,
		array $datasetChecksums
	) {
		$this->applicationVersion = $applicationVersion;
		$this->formatVersion = $formatVersion;
		$this->datasetChecksums = $datasetChecksums;
	}

	/** @return array */
	public function getDatasetChecksums(): array {
		return $this->datasetChecksums;
	}

	/** @return int */
	public function getFormatVersion(): int {
		return $this->formatVersion;
	}

	/** @return string */
	public function getApplicationVersion(): string {
		return $this->applicationVersion;
	}

	/** @return array */
	public function toArray(): array {
		return [
			'application_version' => $this->applicationVersion,
			'dataset_checksums' => $this->datasetChecksums,
			'format_version' => $this->formatVersion
		];
	}

}
