<?php

namespace GrowthExperiments\HelpPanel\Tips;

class LinkTipTree extends TipTree {

	/** @inheritDoc */
	public function getTree(): array {
		$steps = [
			'value' => [ 'main' => [], 'example' => [] ],
			'calm' => [ 'main' => [] ],
			'rules1' => [ 'main' => [], 'example' => [], 'text' => [] ],
			'step1' => [
				'main' => [ $this->getEditMessageTipConfigData() ],
				'example' => [],
			],
			'step2' => [
				'main' => [
					[
						'type' => self::TIP_DATA_TYPE_PLAIN_MESSAGE,
						'data' => 'visualeditor-annotationbutton-link-tooltip',
					],
					[
						'type' => self::TIP_DATA_TYPE_OOUI_ICON,
						'data' => [ 'icon' => 'link' ],
					],
				],
			],
			'publish' => [
				'main' => [ $this->getPublishMessageTipConfigData() ],
			],
		];
		return $this->maybeAddLearnMoreLinkTipNode( $steps );
	}

	/** @inheritDoc */
	protected function getTaskTypeId(): string {
		return 'links';
	}
}
