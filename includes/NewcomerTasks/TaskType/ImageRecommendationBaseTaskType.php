<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MessageLocalizer;

abstract class ImageRecommendationBaseTaskType extends TaskType {

	/** @see ::getMaxTasksPerDay */
	public const FIELD_MAX_TASKS_PER_DAY = 'maxTasksPerDay';
	/** @see ::getMinimumCaptionCharacterLength */
	public const FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH = 'minimumCaptionCharacterLength';
	/** @see ::getMinimumImageSize */
	public const FIELD_MINIMUM_IMAGE_SIZE = 'minimumImageSize';

	public const VALID_MEDIA_TYPES = [
		MEDIATYPE_BITMAP,
		MEDIATYPE_DRAWING
	];

	public const DEFAULT_SETTINGS = [
		self::FIELD_MAX_TASKS_PER_DAY => 25,
		self::FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH => 5,
		self::FIELD_MINIMUM_IMAGE_SIZE => [
			'width' => 100
		]
	];

	/** @inheritDoc */
	protected const IS_MACHINE_SUGGESTION = true;

	/** @var int */
	protected $maxTasksPerDay;
	/** @var int */
	protected $minimumCaptionCharacterLength;
	/** @var array */
	protected $minimumImageSize;

	/**
	 * @inheritDoc
	 * @param array $settings A settings array matching ImageRecommendationTaskType::DEFAULT_SETTINGS
	 */
	public function __construct(
		$id,
		$difficulty,
		array $settings = [],
		array $extraData = [],
		array $excludedTemplates = [],
		array $excludedCategories = []
	) {
		parent::__construct( $id, $difficulty, $extraData, $excludedTemplates, $excludedCategories );
		$settings += self::DEFAULT_SETTINGS;
		$this->maxTasksPerDay = $settings[self::FIELD_MAX_TASKS_PER_DAY];
		$this->minimumCaptionCharacterLength = $settings[self::FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH];
		$this->minimumImageSize = $settings[self::FIELD_MINIMUM_IMAGE_SIZE];
	}

	/**
	 * Return the filters to apply to the recommendation
	 *
	 * @return array an array with the following fields:
	 *   - minimumSize: an array [ 'width' => int ] containing the minimum width
	 *   - validMediaTypes: an array of valid media types (MEDIATYPE_* constants)
	 */
	public function getSuggestionFilters(): array {
		return [
			'minimumSize' => $this->minimumImageSize,
			'validMediaTypes' => self::VALID_MEDIA_TYPES
		];
	}

	/**
	 * The maximum number of image recommendation tasks that a user can perform each calendar day.
	 */
	public function getMaxTasksPerDay(): int {
		return $this->maxTasksPerDay;
	}

	/**
	 * The minimum number of characters needed for a caption to be submittable.
	 */
	public function getMinimumCaptionCharacterLength(): int {
		return $this->minimumCaptionCharacterLength;
	}

	/** @inheritDoc */
	public function shouldOpenInEditMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function getDefaultEditSection(): string {
		return 'all';
	}

	/** @inheritDoc */
	public function getQualityGateIds(): array {
		return [ 'dailyLimit' ];
	}

	/** @inheritDoc */
	public function getViewData( MessageLocalizer $messageLocalizer ) {
		return parent::getViewData( $messageLocalizer ) + [
				'maxTasksPerDay' => $this->maxTasksPerDay,
				'minimumCaptionCharacterLength' => $this->minimumCaptionCharacterLength,
				'minimumImageSize' => $this->minimumImageSize,
			];
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		return parent::toJsonArray() + [
				'settings' => [
					'maxTasksPerDay' => $this->maxTasksPerDay,
					'minimumCaptionCharacterLength' => $this->minimumCaptionCharacterLength,
					'minimumImageSize' => $this->minimumImageSize,
				],
			];
	}

}
