<?php

namespace GrowthExperiments\HelpPanel\Tips;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;

class ImageRecommendationTipTree extends TipTree {

	/** @inheritDoc */
	public function getTree(): array {
		// TODO: This is all placeholder material; update in a later patch.
		$steps = [
			'onboarding' => [
				'header' => [],
				'main' => []
			]
		];
		return $this->maybeAddLearnMoreLinkTipNode( $steps, 'onboarding' );
	}

	/** @inheritDoc */
	protected function getTaskTypeId(): string {
		return ImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
	}
}
