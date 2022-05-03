var CollapsibleDrawer = require( '../ui-components/CollapsibleDrawer.js' );

/**
 * Post-edit drawer
 *
 * @class mw.libs.ge.PostEditDrawer
 * @extends mw.libs.ge.CollapsibleDrawer
 *
 * @param {PostEditPanel} postEditPanel
 * @param {mw.libs.ge.HelpPanelLogger} helpPanelLogger
 * @constructor
 */
function PostEditDrawer( postEditPanel, helpPanelLogger ) {
	this.toastMessage = postEditPanel.getPostEditToastMessage();
	this.shouldShowToastMessageWithDrawer = OO.ui.isMobile();
	this.panel = postEditPanel;
	this.logger = helpPanelLogger;
	PostEditDrawer.super.call( this, {
		headerText: this.panel.getHeaderText(),
		$introContent: this.shouldShowToastMessageWithDrawer ? this.toastMessage.$element : null,
		content: [ this.panel.getMainArea() ].concat( this.panel.getFooterButtons() ),
		padded: false
	} );
	this.$element.addClass( [
		'mw-ge-postEditDrawer',
		OO.ui.isMobile() ? 'mw-ge-postEditDrawer-mobile' : 'mw-ge-postEditDrawer-desktop'
	] );
}

OO.inheritClass( PostEditDrawer, CollapsibleDrawer );

/**
 * Show the toast message at the top of the page (similar to mw.notify but the notification itself
 * is a centered MessageWidget)
 *
 * @param {number} [delay] Delay in milliseconds before proceeding to the next action
 * @return {jQuery.Promise} Promise that resolves when the notification has been shown
 */
PostEditDrawer.prototype.showToastMessage = function ( delay ) {
	var promise = $.Deferred(),
		$toastMessageArea = $( '<div>' ).addClass( 'mw-ge-postEditDrawer-toastMessageArea' ),
		$toastMessageOverlay = $( '<div>' ).addClass(
			'mw-ge-postEditDrawer-toastMessageOverlay'
		).append( $toastMessageArea );
	$( document.body ).append( $toastMessageOverlay );
	$toastMessageArea.append( this.toastMessage.$element );
	this.toastMessage.on( 'hide', function () {
		$toastMessageOverlay.detach();
	} );
	setTimeout( function () {
		promise.resolve();
	}, delay );
	return promise;
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
