( function () {
	/**
	 * Help Panel CTA Button
	 *
	 * @extends OO.ui.ButtonWidget
	 *
	 * @param {Object} [config]
	 * @param {string} [config.isOpened] Whereas to display the button in the
	 * closed state showing a question mark sign or open state showing an arrow indicator
	 * @param {string} [config.label] Label to display
	 * @param {string} [config.href] Link to navigate
	 * @constructor
	 */
	function HelpPanelButton( config ) {
		const isOpened = typeof config.isOpened === 'boolean' ? config.isOpened : false;
		const defaults = {
			id: 'mw-ge-help-panel-cta-button',
			invisibleLabel: true,
			flags: [ 'progressive' ],
			classes: [ 'mw-ge-help-panel-button', isOpened ? 'mw-ge-help-panel-button--opened' : '' ],
			// Both icon and indicator HTML elements are present inside the button,
			// only one of these two is visible at a time, with a transition between them
			// See HelpPanelButton.less
			icon: 'help',
			indicator: 'up',
		};
		config = Object.assign( {}, defaults, config );
		HelpPanelButton.super.call( this, config );

		this.state = { isOpened: isOpened };

		this.on( 'click', () => {
			this.setOpen( !this.state.isOpened );
		} );
	}

	OO.inheritClass( HelpPanelButton, OO.ui.ButtonWidget );

	HelpPanelButton.prototype.setOpen = function ( value ) {
		if ( this.state.isOpened !== value ) {
			this.state.isOpened = value;
			this.$element.toggleClass( 'mw-ge-help-panel-button--opened', value );
		}
	};

	// So when infused OOUI can match the class name (mw.libs.ge.HelpPanelButton)
	// set on server in HelpPanelButton::getJavaScriptClassName
	window.mw.libs.ge = window.mw.libs.ge || {};
	mw.libs.ge.HelpPanelButton = HelpPanelButton;

	module.exports = HelpPanelButton;

}() );
