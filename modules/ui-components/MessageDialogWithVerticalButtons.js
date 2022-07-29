/**
 * Depends on oojs-ui-windows.
 */
( function () {
	/**
	 * MessageDialog variant which displays its action buttons vertically.
	 *
	 * @class
	 * @extends OO.ui.MessageDialog
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options, passed to MessageDialog
	 */
	function MessageDialogWithVerticalButtons( config ) {
		MessageDialogWithVerticalButtons.super.call( this, config );
	}
	OO.inheritClass( MessageDialogWithVerticalButtons, OO.ui.MessageDialog );

	/** @private */
	MessageDialogWithVerticalButtons.prototype.fitActions = function () {
		// Overriding a private method is not nice, but other methods of wrangling
		// MessageDialog's buttons to be vertical wouldn't be nice either.
		var previous = this.verticalActionLayout;
		this.toggleVerticalActionLayout( true );
		this.$body.css( 'bottom', this.$foot.outerHeight( true ) );
		if ( this.verticalActionLayout !== previous ) {
			this.updateSize();
		}
	};

	module.exports = MessageDialogWithVerticalButtons;
}() );
