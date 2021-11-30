<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Json\JsonUnserializer;

class ImageRecommendationTaskType extends TaskType {

	/** @see ::getMaxTasksPerDay */
	public const FIELD_MAX_TASKS_PER_DAY = 'maxTasksPerDay';
	/** @see ::getMinimumCaptionCharacterLength */
	public const FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH = 'minimumCaptionCharacterLength';
	/** @see ::getMinimumImageSize */
	public const FIELD_MINIMUM_IMAGE_SIZE = 'minimumImageSize';

	public const DEFAULT_SETTINGS = [
		self::FIELD_MAX_TASKS_PER_DAY => 25,
		self::FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH => 5,
		self::FIELD_MINIMUM_IMAGE_SIZE => [
			'width' => 100
		]
	];

	/** @var int */
	protected $maxTasksPerDay;
	/** @var int */
	protected $minimumCaptionCharacterLength;
	/** @var array */
	protected $minimumImageSize;

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
		$this->minimumImageSize = $settings[self::FIELD_MINIMUM_IMAGE_SIZE];
	}

	/**
	 * An array [ 'width' => int ] containing the minimum width and other metadata thresholds for an image to be
	 * included as a recommendation
	 * @return array
	 */
	public function getMinimumImageSize(): array {
		return $this->minimumImageSize;
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

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return parent::toJsonArray() + [
			'settings' => [
				'maxTasksPerDay' => $this->maxTasksPerDay,
				'minimumCaptionCharacterLength' => $this->minimumCaptionCharacterLength,
				'minimumImageSize' => $this->minimumImageSize,
			],
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		$taskType = new ImageRecommendationTaskType(
			$json['id'],
			$json['difficulty'],
			$json['settings'],
			$json['extraData'],
			self::getExcludedTemplatesTitleValues( $json ),
			self::getExcludedCategoriesTitleValues( $json )
		);
		$taskType->setHandlerId( $json['handlerId'] );
		return $taskType;
	}

}
