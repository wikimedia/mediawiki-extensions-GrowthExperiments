const CollapsibleDrawer = require( '../ui-components/CollapsibleDrawer.js' ),
	TryNewTaskPanel = require( './TryNewTaskPanel.js' );

/**
 * Post-edit drawer
 *
 * @class mw.libs.ge.PostEditDrawer
 * @extends mw.libs.ge.CollapsibleDrawer
 *
 * @param {mw.libs.ge.PostEditPanel|mw.libs.ge.TryNewTaskPanel} panel
 * @param {mw.libs.ge.HelpPanelLogger} helpPanelLogger
 * @constructor
 */
function PostEditDrawer( panel, helpPanelLogger ) {
	this.toastMessage = panel.getPostEditToastMessage();
	this.shouldShowToastMessageWithDrawer = OO.ui.isMobile();
	this.panel = panel;
	this.logger = helpPanelLogger;
	PostEditDrawer.super.call( this, {
		headerText: this.panel.getHeaderText(),
		$introContent: this.shouldShowToastMessageWithDrawer ? this.toastMessage.$element : null,
		content: [ this.panel.getMainArea() ].concat( this.panel.getFooterButtons() ),
		padded: false
	} );
	this.$element.addClass( [
		'mw-ge-postEditDrawer',
		OO.ui.isMobile() ? 'mw-ge-postEditDrawer-mobile' : 'mw-ge-postEditDrawer-desktop',
		panel instanceof TryNewTaskPanel ? 'mw-ge-postEditDrawer-tryNewTask' : 'mw-ge-postEditDrawer-postEdit'
	] );
	// Allow close events emitted in the panel to close the drawer.
	this.panel.connect( this, {
		close: 'onClose'
	} );
}

OO.inheritClass( PostEditDrawer, CollapsibleDrawer );

/**
 * @param {Mixed} [closeData] Data to pass to the close handler for CollapsibleDrawer.
 */
PostEditDrawer.prototype.onClose = function ( closeData ) {
	this.close( closeData );
};

/**
 * Show the toast message at the top of the page (similar to mw.notify but the notification itself
 * is a centered MessageWidget)
 *
 * @param {number} [delay] Delay in milliseconds before proceeding to the next action
 * @return {jQuery.Promise} Promise that resolves when the notification has been shown
 */
PostEditDrawer.prototype.showToastMessage = function ( delay ) {
	const deferred = $.Deferred(),
		$toastMessageArea = $( '<div>' ).addClass( 'mw-ge-postEditDrawer-toastMessageArea' ),
		$toastMessageOverlay = $( '<div>' ).addClass(
			'mw-ge-postEditDrawer-toastMessageOverlay'
		).append( $toastMessageArea );
	$( document.body ).append( $toastMessageOverlay );
	$toastMessageArea.append( this.toastMessage.$element );
	this.toastMessage.on( 'hide', () => {
		$toastMessageOverlay.detach();
	} );
	setTimeout( () => {
		deferred.resolve();
	}, delay );
	return deferred.promise();
};

/**
 * Show the toast message and animate in the drawer
 *
 * @return {mw.libs.ge.CollapsibleDrawer}
 */
PostEditDrawer.prototype.showWithToastMessage = function () {
	this.logger.log( 'postedit-toast-message-impression' );
	if ( this.shouldShowToastMessageWithDrawer ) {
		return this.openWithIntroContent();
	}
	this.showToastMessage( 400 ).then( this.open.bind( this ) );
	return this;
};

/** @inheritDoc */
PostEditDrawer.prototype.expand = function () {
	this.logger.log( 'postedit-expand' );
	PostEditDrawer.super.prototype.expand.call( this );
};

/** @inheritDoc */
PostEditDrawer.prototype.collapse = function () {
	this.logger.log( 'postedit-collapse' );
	PostEditDrawer.super.prototype.collapse.call( this );
};

module.exports = PostEditDrawer;
