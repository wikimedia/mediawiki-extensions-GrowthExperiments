<?php

namespace GrowthExperiments\HelpPanel\Tips;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;

class ImageRecommendationTipTree extends TipTree {

	/** @inheritDoc */
	public function getTree(): array {
		return [
			'value' => [
				'header' => [],
				'main-multiple' => [],
				'text' => [
					[
						'type' => self::TIP_DATA_TYPE_TEXT_VARIANT,
						'data' => 'italic',
					],
				],
			],
			'step1' => [
				'header' => [],
				'main-multiple' => [],
			],
			'step2' => [
				'header' => [],
				'main' => [],
			],
			'step3' => [
				'header' => [],
				'main-multiple' => [],
			],
		];
	}

	/** @inheritDoc */
	protected function getTaskTypeId(): string {
		return ImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
	}
}
