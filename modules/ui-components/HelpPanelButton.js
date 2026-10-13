( function () {
	/**
	 * Help Panel CTA Button
	 *
	 * @extends OO.ui.ToggleButtonWidget
	 *
	 * @param {Object} [config]
	 * @param {string} [config.label] Label to display
	 * @param {string} [config.href] Link to navigate
	 * @constructor
	 */
	function HelpPanelButton( config ) {
		const defaults = {
			id: 'mw-ge-help-panel-cta-button',
			invisibleLabel: true,
			flags: [ 'progressive' ],
			classes: [ 'mw-ge-help-panel-button' ],
			size: 'large',
			icon: 'help',
		};
		config = Object.assign( {}, defaults, config );
		HelpPanelButton.super.call( this, config );

		this.$openIcon = $( '<div>' ).addClass( 'oo-ui-iconElement-icon oo-ui-icon-expand oo-ui-image-invert mw-ge-help-panel-openIcon' );
		this.$button.prepend( this.$openIcon );

	}

	OO.inheritClass( HelpPanelButton, OO.ui.ToggleButtonWidget );

	// So when infused OOUI can match the class name (mw.libs.ge.HelpPanelButton)
	// set on server in HelpPanelButton::getJavaScriptClassName
	window.mw.libs.ge = window.mw.libs.ge || {};
	mw.libs.ge.HelpPanelButton = HelpPanelButton;

	module.exports = HelpPanelButton;

}() );
