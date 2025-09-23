/**
 * Toast message shown along with the post-edit drawer
 *
 * @class mw.libs.ge.PostEditToastMessage
 * @extends OO.ui.MessageWidget
 *
 * @param {Object} config Same as OO.ui.MessageWidget with the addition of autoHideDuration
 * @param {number} [config.autoHideDuration] Number of milliseconds after which the message is hidden
 *
 * @constructor
 */
function PostEditToastMessage( config ) {
	PostEditToastMessage.super.call( this, config );
	this.isHidden = false;
	if ( config.autoHideDuration ) {
		const autohide = setTimeout( this.hide.bind( this ), config.autoHideDuration );
		this.$element.on( 'click', () => {
			clearTimeout( autohide );
			this.hide();
		} );
	}
	this.$element.addClass( [
		'mw-ge-postEditToastMessage',
		'mw-ge-postEditToastMessage--hidden',
		OO.ui.isMobile() ?
			'mw-ge-postEditToastMessage-mobile' :
			'mw-ge-postEditToastMessage-desktop',
	] );
	setTimeout( () => {
		this.toggleHiddenState( false );
	}, 200 );
}

OO.inheritClass( PostEditToastMessage, OO.ui.MessageWidget );

/**
 * Show or hide the message
 *
 * @param {boolean} isHidden Whether the message is hidden
 */
PostEditToastMessage.prototype.toggleHiddenState = function ( isHidden ) {
	this.isHidden = isHidden;
	this.$element.toggleClass( 'mw-ge-postEditToastMessage--hidden', isHidden );
	if ( isHidden ) {
		this.emit( 'hide' );
	}
};

/**
 * Hide the message
 */
PostEditToastMessage.prototype.hide = function () {
	this.toggleHiddenState( true );
};

module.exports = PostEditToastMessage;
