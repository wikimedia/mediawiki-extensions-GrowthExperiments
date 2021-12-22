( function () {
	'use strict';

	/**
	 * @param {string} name Tab name
	 * @param {Object} config
	 * @param {string} config.taskType Task type ID
	 * @param {string} config.label Tab label
	 * @param {Object<string,string>} config.data Tab contents as a set of paragraphs
	 * @constructor
	 */
	function QuickStartTipsTabPanelLayout( name, config ) {
		var key, panel;
		QuickStartTipsTabPanelLayout.super.call( this, name,
			$.extend( { scrollable: false, expanded: false }, config )
		);
		this.stackLayout = new OO.ui.StackLayout( {
			continuous: true,
			expanded: false,
			scrollable: false
		} );
		for ( key in config.data ) {
			panel = new OO.ui.PanelLayout( {
				padded: false,
				expanded: false
			} );
			panel.$element.append( config.data[ key ] );
			// Generate data-link-id dynamically for guidance content, which is defined in
			// system messages so assigning link IDs manually would be awkward.
			// The format is guidance-<task type>-tipset-<tab index> (1-based).
			panel.$element.find( 'a' ).attr( 'data-link-id', 'guidance-' + config.taskType + '-' + name );
			this.stackLayout.addItems( [ panel ] );
		}
		this.$element.append( this.stackLayout.$element );
	}

	OO.inheritClass( QuickStartTipsTabPanelLayout, OO.ui.TabPanelLayout );

	module.exports = QuickStartTipsTabPanelLayout;

}() );
