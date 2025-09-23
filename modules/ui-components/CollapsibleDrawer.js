/**
 * Drawer attached to the bottom of the screen that can be collapsed and expanded
 *
 * @class mw.libs.ge.CollapsibleDrawer
 *
 * @param {Object} [config]
 * @param {*[]} config.content Array of elements to show for the drawer content
 * @param {jQuery} [config.$introContent] Element to show before presenting the drawer
 * @param {string} config.headerText Text to show in the header
 * @param {string[]} [config.classes] Classname(s) of the drawer
 * @param {boolean} [config.padded] Whether the drawer should be padded, defaults to true
 *
 * @constructor
 */
function CollapsibleDrawer( config ) {
	const panelLayoutConfig = {
			expanded: false,
			padded: typeof config.padded === 'boolean' ? config.padded : true,
			content: config.content,
		},
		panelLayout = new OO.ui.PanelLayout( panelLayoutConfig );
	this.isIntroContentHidden = true;
	this.isContentHidden = true;
	this.contentAnimationDelay = 200;
	if ( config.$introContent ) {
		this.$introContent = config.$introContent;
		this.$introContent.addClass( [
			'mw-ge-collapsibleDrawer-introContent',
			'mw-ge-collapsibleDrawer-introContent--hidden',
		] );
	}
	this.opening = $.Deferred();
	this.opened = $.Deferred();
	this.closing = $.Deferred();
	this.closed = $.Deferred();
	this.$element = $( '<div>' ).addClass( [
		'mw-ge-collapsibleDrawer',
		'mw-ge-collapsibleDrawer--animate-in',
		OO.ui.isMobile() ? 'mw-ge-collapsibleDrawer-mobile' : 'mw-ge-collapsibleDrawer-desktop',
		panelLayoutConfig.padded ? 'mw-ge-collapsibleDrawer--padded' : '',
	] );
	this.$content = $( '<div>' ).addClass( 'mw-ge-collapsibleDrawer-content' );
	this.setupHeader( config.headerText );
	this.$content.append( this.$head, panelLayout.$element );
	this.$element.append( this.$introContent, this.$content );
	if ( config.classes ) {
		this.$element.addClasses( config.classes );
	}
	$( document.body ).addClass(
		OO.ui.isMobile() ?
			'mw-ge-body--with-collapsibleDrawer-mobile' :
			'mw-ge-body--with-collapsibleDrawer-desktop',
	);
	$( document ).on( 'keyup', this.onEscapeKeyUp.bind( this ) );
}

/**
 * Handle "Escape" keyup events to enable closing the drawer.
 * First stroke will collapse it and second will close it.
 *
 * @param {jQuery.event} e
 */
CollapsibleDrawer.prototype.onEscapeKeyUp = function ( e ) {
	if ( e.key === 'Escape' || e.keyCode === OO.ui.Keys.ESCAPE ) {
		if ( this.isContentHidden ) {
			this.close();
		} else {
			this.collapse();
		}
	}
};

/**
 * Expand the drawer
 */
CollapsibleDrawer.prototype.expand = function () {
	this.isContentHidden = false;
	this.chevronIcon.setIcon( 'expand' );
	this.$element.removeClass( 'mw-ge-collapsibleDrawer--collapsed' );
};

/**
 * Collapse the drawer
 */
CollapsibleDrawer.prototype.collapse = function () {
	this.isContentHidden = true;
	this.chevronIcon.setIcon( 'collapse' );
	this.$element.addClass( 'mw-ge-collapsibleDrawer--collapsed' );
};

/**
 * Show the drawer if it's hidden, hide it if it's shown
 */
CollapsibleDrawer.prototype.toggleDisplayState = function () {
	if ( this.isContentHidden ) {
		this.expand();
	} else {
		this.hideIntroContent()
			.then( this.collapse.bind( this ) );
	}
};

/**
 * Set up the header text, close icon and chevron icon
 *
 * @param {string} [headerText]
 */
CollapsibleDrawer.prototype.setupHeader = function ( headerText ) {
	// this.closeIconBtn visibility is toggled using CSS classes
	// that modify the opacity for animation purposes. It is only
	// shown when the drawer is open
	this.closeIconBtn = new OO.ui.ButtonWidget( {
		classes: [ 'mw-ge-collapsibleDrawer-close-icon' ],
		framed: false,
		icon: 'close',
	} );
	this.chevronIcon = new OO.ui.IconWidget( {
		classes: [ 'mw-ge-collapsibleDrawer-chevron' ],
		framed: false,
		icon: 'expand',
	} );
	this.$head = $( '<div>' ).addClass( 'mw-ge-collapsibleDrawer-header' );
	this.$headerText = $( '<div>' ).addClass( 'mw-ge-collapsibleDrawer-headerText' )
		.append( [
			this.closeIconBtn.$element,
			$( '<div>' ).addClass( 'mw-ge-collapsibleDrawer-headerText-text' ).text( headerText ),
		] );
	this.$head.attr( 'role', 'button' ).append( [
		this.$headerText,
		this.chevronIcon.$element,
	] );
	this.$head.on( 'click', this.toggleDisplayState.bind( this ) );
	this.closeIconBtn.on( 'click', this.close.bind( this ) );
};

/**
 * Open the drawer, used when showing the drawer for the first time
 *
 * @param {number} [delay] Delay in milliseconds before animating in the drawer
 * @return {CollapsibleDrawer}
 */
CollapsibleDrawer.prototype.open = function ( delay ) {
	this.$element.on( 'transitionend', () => {
		this.isContentHidden = false;
		this.opened.resolve();
		this.$element.off( 'transitionend' );
	} );
	setTimeout( () => {
		this.$element.removeClass( 'mw-ge-collapsibleDrawer--animate-in' );
	}, delay );
	this.opening.resolve();
	return this;
};

/**
 * Close the drawer and detach it from the document
 *
 * @param {Mixed} [closeData] Data to return with the closed promise.
 * @return {CollapsibleDrawer}
 */
CollapsibleDrawer.prototype.close = function ( closeData ) {
	const onDialogHidden = function () {
		this.closed.resolve( closeData );
		this.$element.detach();
	}.bind( this );
	this.closing.resolve();
	if ( this.isContentHidden ) {
		onDialogHidden();
		return this;
	}
	this.$element.addClass( 'mw-ge-collapsibleDrawer--animate-in' );
	this.$element.on( 'transitionend', onDialogHidden );
	return this;
};

/**
 * Show the intro content and open the drawer, used when showing the drawer for the first time
 *
 * @return {CollapsibleDrawer}
 */
CollapsibleDrawer.prototype.openWithIntroContent = function () {
	if ( this.$introContent ) {
		this.showIntroContent().then( this.open.bind( this ) );
		return this;
	}
	// Delay so that the initial animation is visible
	return this.open( this.contentAnimationDelay );
};

/**
 * Hide the intro content
 *
 * @return {jQuery.Promise} Promise that resolves when the intro content has been hidden
 */
CollapsibleDrawer.prototype.hideIntroContent = function () {
	const deferred = $.Deferred();
	if ( !this.$introContent || this.isIntroContentHidden ) {
		return deferred.resolve().promise();
	}
	this.$introContent.addClass( 'mw-ge-collapsibleDrawer-introContent--hidden' );
	setTimeout( () => {
		this.isIntroContentHidden = true;
		this.$introContent.detach();
		deferred.resolve();
	}, this.contentAnimationDelay );
	return deferred.promise();
};

/**
 * Show the intro content by itself before animating in the drawer content
 *
 * @return {jQuery.Promise} Promise that resolves when the intro content has been shown
 */
CollapsibleDrawer.prototype.showIntroContent = function () {
	const deferred = $.Deferred();
	// Show only the intro content (including its margin)
	this.$element.css( 'bottom', this.$introContent.outerHeight( true ) );
	this.$introContent.removeClass( 'mw-ge-collapsibleDrawer-introContent--hidden' );
	this.isIntroContentHidden = false;
	setTimeout( () => {
		this.$element.css( 'bottom', 0 );
		deferred.resolve();
	}, 600 );
	return deferred.promise();
};

module.exports = CollapsibleDrawer;
