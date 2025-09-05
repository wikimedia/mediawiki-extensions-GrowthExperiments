<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

class SectionImageRecommendationTaskType extends ImageRecommendationBaseTaskType {

	/** @inheritDoc */
	public function getSmallTaskCardImageCssClasses(): array {
		return [ 'mw-ge-small-task-card-image-placeholder' ];
	}

	/** @inheritDoc */
	public function getLearnMoreLink(): ?string {
		return null;
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		$taskType = new static(
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
