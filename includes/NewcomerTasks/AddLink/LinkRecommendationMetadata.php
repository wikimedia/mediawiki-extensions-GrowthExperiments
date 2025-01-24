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
	/** @var int */
	private $taskTimestamp;

	/**
	 * @param string $applicationVersion The git commit hash of the application that generated the recommendation.
	 * @param int $formatVersion The format version for the recommendation.
	 * @param array $datasetChecksums An array of datasets / checksums, keys are the dataset names and values are the
	 *   checksums
	 * @param int $taskTimestamp UNIX timestamp of when the task was created.
	 */
	public function __construct(
		string $applicationVersion,
		int $formatVersion,
		array $datasetChecksums,
		int $taskTimestamp
	) {
		$this->applicationVersion = $applicationVersion;
		$this->formatVersion = $formatVersion;
		$this->datasetChecksums = $datasetChecksums;
		$this->taskTimestamp = $taskTimestamp;
	}

	public function getDatasetChecksums(): array {
		return $this->datasetChecksums;
	}

	public function getFormatVersion(): int {
		return $this->formatVersion;
	}

	public function getApplicationVersion(): string {
		return $this->applicationVersion;
	}

	public function getTaskTimestamp(): int {
		return $this->taskTimestamp;
	}

	public function toArray(): array {
		return [
			'application_version' => $this->applicationVersion,
			'dataset_checksums' => $this->datasetChecksums,
			'format_version' => $this->formatVersion,
			'task_timestamp' => $this->taskTimestamp,
		];
	}

}
