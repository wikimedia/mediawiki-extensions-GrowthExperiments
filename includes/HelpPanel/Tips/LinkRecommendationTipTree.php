<?php

namespace GrowthExperiments\HelpPanel\Tips;

class LinkRecommendationTipTree extends TipTree {

	/** @inheritDoc */
	public function getTree(): array {
		$steps = [
			'value' => [
				'header' => [],
				'main' => [],
				'example' => [ [
					'type' => self::TIP_DATA_TYPE_PLAIN_MESSAGE,
					'data' => [ 'labelKey' =>
						'growthexperiments-help-panel-suggestededits-tips-link-recommendation-example-label',
					],
				] ],
				'text' => [],
			],
			'calm' => [
				'header' => [],
				'main' => [],
			],
			'rules1' => [
				'header' => [],
				'main' => [],
			],
		];
		return $this->maybeAddLearnMoreLinkTipNode( $steps, 'calm' );
	}

	/** @inheritDoc */
	protected function getTaskTypeId(): string {
		return 'link-recommendation';
	}
}
