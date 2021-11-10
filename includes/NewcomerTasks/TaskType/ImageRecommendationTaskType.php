<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

class ImageRecommendationTaskType extends TaskType {

	/** @see ::getMaxTasksPerDay */
	public const FIELD_MAX_TASKS_PER_DAY = 'maxTasksPerDay';
	/** @see ::getMinimumCaptionCharacterLength */
	public const FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH = 'minimumCaptionCharacterLength';

	public const DEFAULT_SETTINGS = [
		self::FIELD_MAX_TASKS_PER_DAY => 25,
		self::FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH => 5
	];

	/** @var int */
	protected $maxTasksPerDay;
	/** @var int */
	protected $minimumCaptionCharacterLength;

	/** @var bool */
	protected $isMachineSuggestion = true;

	/** @var bool */
	protected $isMobileOnly = true;

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
	}

	/**
	 * The maximum number of image recommendation tasks that a user can perform each calendar day.
	 *
	 * @return int
	 */
	public function getMaxTasksPerDay(): int {
		return $this->maxTasksPerDay;
	}

	/**
	 * The minimum number of characters needed for a caption to be submittable.
	 *
	 * @return int
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
	public function getSmallTaskCardImageCssClasses(): array {
		return [ 'mw-ge-small-task-card-image-placeholder' ];
	}

	/** @inheritDoc */
	public function getQualityGateIds(): array {
		return [ 'mobileOnly', 'dailyLimit' ];
	}

	/** @inheritDoc */
	public function getLearnMoreLink(): ?string {
		return null;
	}

}
