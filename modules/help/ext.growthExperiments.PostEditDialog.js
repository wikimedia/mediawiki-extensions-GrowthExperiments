'use strict';

( function () {

	/**
	 * @class
	 * @extends OO.ui.ProcessDialog
	 *
	 * @constructor
	 * @param {Object} config
	 * @param {PostEditPanel} config.panel
	 */
	function PostEditDialog( config ) {
		PostEditDialog.super.call( this, {
			classes: [ 'mw-ge-help-panel-postedit-dialog' ]
		} );
		this.panel = config.panel;
	}
	OO.inheritClass( PostEditDialog, OO.ui.ProcessDialog );
	PostEditDialog.static.name = 'postEditDialog';
	PostEditDialog.static.size = 'medium';
	PostEditDialog.static.title = mw.message( 'growthexperiments-help-panel-postedit-header' ).text();
	PostEditDialog.static.actions = [
		{ icon: 'close', action: 'cancel', flags: [ 'safe', 'close' ] }
	];

	PostEditDialog.prototype.initialize = function () {
		PostEditDialog.super.prototype.initialize.call( this );

		this.$content.prepend(
			$( '<div>' )
				.addClass( 'mw-ge-help-panel-postedit-message-anchor' )
				.append( this.panel.getSuccessMessage().$element )
		);
		this.$body.append( this.panel.getMainArea() );
		this.$body.append.apply( this.$body, this.panel.getFooterButtons() );
	};

	PostEditDialog.prototype.getActionProcess = function ( action ) {
		return PostEditDialog.super.prototype.getActionProcess.call( this, action )
			.next( function () {
				if ( action === 'cancel' ) {
					this.close( { action: 'cancel' } );
				}
			}, this );
	};

	module.exports = PostEditDialog;
}() );
