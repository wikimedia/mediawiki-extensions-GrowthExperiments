<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Json\JsonUnserializer;

class ImageRecommendationTaskType extends ImageRecommendationBaseTaskType {

	/** @inheritDoc */
	public function getSmallTaskCardImageCssClasses(): array {
		return [ 'mw-ge-small-task-card-image-placeholder' ];
	}

	/** @inheritDoc */
	public function getLearnMoreLink(): ?string {
		return null;
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
