<?php

namespace GrowthExperiments\HelpPanel;

class HelpPanelButton extends \OOUI\ButtonWidget {
	// This will infuse to a ToggleButtonWidget, but that doesn't exist as a no-JS widget.

	public function __construct( array $config = [] ) {
		// HelpPanelButton default config values need to be in sync with
		// defaults in modules/ui-components/HelpPanelButton.js
		// HelpPanelButton id is used when "infused" on the client
		// side (ie: modules/ext.growthExperiments.HelpPanel/HelpPanelCta.js)
		$config = array_merge( [
			'id' => 'mw-ge-help-panel-cta-button',
			'classes' => [ 'mw-ge-help-panel-button' ],
			'size' => 'large',
			'target' => '_blank',
			'invisibleLabel' => true,
			'infusable' => true,
			'icon' => 'help',
			'flags' => [ 'progressive' ],
		], $config );

		parent::__construct( $config );
	}

	/** @inheritDoc */
	protected function getJavaScriptClassName() {
		return 'mw.libs.ge.HelpPanelButton';
	}
}
