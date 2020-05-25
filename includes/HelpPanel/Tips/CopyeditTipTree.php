<?php

namespace GrowthExperiments\HelpPanel\Tips;

class CopyeditTipTree extends TipTree {

	/** @inheritDoc */
	public function getTree(): array {
		$steps = [
			'value' => [ 'main' => [], 'example' => [], ],
			'calm' => [
				'main' => [],
				'graphic' => [
					[
						'type' => self::TIP_DATA_TYPE_IMAGE,
						'data' => [
							'filename' => 'intro-typo',
							'suffix' => 'svg'
						]
					]
				]
			],
			'rules1' => [ 'main' => [], 'example' => [], ],
			'rules2' => [ 'main' => [], 'example' => [], 'text' => [] ],
			'step1' => [ 'main' => [ $this->getEditMessageTipConfigData() ] ],
			'publish' => [
				'main' => [ $this->getPublishMessageTipConfigData() ]
			]
		];
		return $this->maybeAddLearnMoreLinkTipNode( $steps );
	}

}
