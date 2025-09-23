<?php

namespace GrowthExperiments\HelpPanel\Tips;

class ExpandTipTree extends TipTree {

	/** @inheritDoc */
	public function getTree(): array {
		$steps = [
			'value' => [ 'main' => [] ],
			'calm' => [ 'main' => [] ],
			'rules1' => [ 'main' => [] ],
			'step1' => [ 'main' => [ $this->getEditMessageTipConfigData() ] ],
			'step2' => [
				'main' => [
					[
						'type' => self::TIP_DATA_TYPE_TITLE,
						'data' => [
							'title' => $this->extraData['references']['learnMoreLink'] ?? null,
							'messageKeyVariant' => '-no-link',
						],
					],
				],
			],
			'publish' => [ 'main' => [ $this->getPublishMessageTipConfigData() ] ],
		];
		return $this->maybeAddLearnMoreLinkTipNode( $steps );
	}

	/** @inheritDoc */
	protected function getTaskTypeId(): string {
		return 'expand';
	}
}
