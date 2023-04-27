<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Json\JsonUnserializer;

class SectionImageRecommendationTaskType extends TaskType {
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
