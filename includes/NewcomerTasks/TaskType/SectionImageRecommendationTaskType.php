<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Json\JsonUnserializer;

class SectionImageRecommendationTaskType extends TaskType {

	public const VALID_MEDIA_TYPES = [
		MEDIATYPE_BITMAP,
		MEDIATYPE_DRAWING
	];

	public const DEFAULT_SETTINGS = [];

	/** @inheritDoc */
	protected const IS_MACHINE_SUGGESTION = true;

	/**
	 * @inheritDoc
	 * @param array $settings A settings array matching SectionImageRecommendationTaskType::DEFAULT_SETTINGS
	 */
	public function __construct(
		$id,
		$difficulty,
		array $settings = []
	) {
		parent::__construct( $id, $difficulty );
		$settings += self::DEFAULT_SETTINGS;
	}

	/**
	 * Return the filters to apply to the recommendation
	 *
	 * @return array an array with the following fields:
	 *   - validMediaTypes: an array of valid media types [ 'BITMAP' ]
	 */
	public function getSuggestionFilters(): array {
		return [
			'validMediaTypes' => self::VALID_MEDIA_TYPES
		];
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return parent::toJsonArray() + [
			'settings' => [],
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		$taskType = new SectionImageRecommendationTaskType(
			$json['id'],
			$json['difficulty'],
			$json['settings']
		);
		$taskType->setHandlerId( $json['handlerId'] );
		return $taskType;
	}
}
