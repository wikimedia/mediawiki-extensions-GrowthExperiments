<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use OOUI\ButtonWidget;

class PreviousNext extends ButtonWidget {

	/** @inheritDoc */
	public function __construct( array $config = [] ) {
		parent::__construct( array_merge(
			$config,
			[
				'icon' => 'arrow' . $config['direction'],
				'framed' => false,
				'disabled' => true,
				'invisibleLabel' => true,
				'label' => $config['message'],
				'classes' => $config['hidden'] ? [ 'oo-ui-element-hidden' ] : [],
			]
		) );
	}

}
