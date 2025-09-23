<?php

namespace GrowthExperiments\HelpPanel\Tips;

class ReferencesTipTree extends TipTree {

	/** @inheritDoc */
	public function getTree(): array {
		$steps = [
			'value' => [ 'main' => [], 'example' => [], 'text' => [] ],
			'calm' => [ 'main' => [] ],
			'rules1' => [ 'main' => [] ],
			'step1' => [ 'main' => [ $this->getEditMessageTipConfigData() ] ],
			'step2' => [
				'main' => [
					[
						'type' => self::TIP_DATA_TYPE_OOUI_ICON,
						'data' => [ 'icon' => 'browser' ],
					],
					[
						'type' => self::TIP_DATA_TYPE_OOUI_ICON,
						'data' => [ 'icon' => 'book' ],
					],
					[
						'type' => self::TIP_DATA_TYPE_OOUI_ICON,
						'data' => [ 'icon' => 'journal' ],
					],
					[
						'type' => self::TIP_DATA_TYPE_OOUI_ICON,
						'data' => [ 'icon' => 'reference' ],
					],
				],
			],
			'step3' => [
				'main' => [
					[
						'type' => self::TIP_DATA_TYPE_PLAIN_MESSAGE,
						'data' => 'cite-ve-toolbar-group-label',
					],
					[
						'type' => self::TIP_DATA_TYPE_OOUI_ICON,
						'data' => [
							'icon' => 'quotes',
							'labelKey' => 'cite-ve-toolbar-group-label',
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
		return 'references';
	}

}
