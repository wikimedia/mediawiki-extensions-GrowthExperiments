var AddLink = require( 'ext.growthExperiments.AddLink' ),
	AddLinkArticleTarget = AddLink.AddLinkArticleTarget,
	MachineSuggestionsMode = AddLink.MachineSuggestionsMode,
	LinkSuggestionInteractionLogger = AddLink.LinkSuggestionInteractionLogger;

/**
 * Desktop version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkDesktopArticleTarget
 * @extends ve.init.mw.DesktopArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddLinkDesktopArticleTarget() {
	AddLinkDesktopArticleTarget.super.apply( this, arguments );
	this.$element.addClass( 've-init-mw-addLinkArticleTarget' );
	this.toolbarScrollOffset = 50;
	this.logger = new LinkSuggestionInteractionLogger( {
		// eslint-disable-next-line camelcase
		is_mobile: false
	} );
}

OO.inheritClass( AddLinkDesktopArticleTarget, ve.init.mw.DesktopArticleTarget );
OO.mixinClass( AddLinkDesktopArticleTarget, AddLinkArticleTarget );

AddLinkDesktopArticleTarget.static.toolbarGroups = [];

AddLinkDesktopArticleTarget.static.actionGroups =
	MachineSuggestionsMode.getActionGroups( AddLinkDesktopArticleTarget.static.actionGroups );

AddLinkDesktopArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response );
	AddLinkDesktopArticleTarget.super.prototype.loadSuccess.call( this, response );
};

AddLinkDesktopArticleTarget.prototype.surfaceReady = function () {
	this.beforeSurfaceReady();
	AddLinkDesktopArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	this.afterSurfaceReady();
};

/**
 * @inheritdoc
 */
AddLinkDesktopArticleTarget.prototype.setupToolbar = function () {
	AddLinkDesktopArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	if ( MachineSuggestionsMode.toolbarHasTitleElement( this.toolbar.$element ) ) {
		this.toolbar.$element.find( '.oo-ui-toolbar-bar' ).first().prepend(
			MachineSuggestionsMode.getTitleElement( { includeIcon: true } )
		);
	}
	MachineSuggestionsMode.trackEditModeClick( this.toolbar.$element );
};

/**
 * @inheritdoc
 */
AddLinkDesktopArticleTarget.prototype.onDocumentKeyDown = function ( e ) {
	// By default, the open toolbar dialog is closed while the editor remains open.
	// In this case, RecommendedLinkToolbarDialog behaves as if it were part of the editing surface,
	// so the editor should be closed upon the first Esc (not the second).
	if ( e.which === OO.ui.Keys.ESCAPE ) {
		e.preventDefault();
		e.stopPropagation();
		this.tryTeardown( false, 'navigate-read' );
		return;
	}
	AddLinkDesktopArticleTarget.super.prototype.onDocumentKeyDown.call( this, e );
};

/**
 * @inheritdoc
 */
AddLinkDesktopArticleTarget.prototype.onBeforeUnload = function () {
	if ( this.hasSwitched ) {
		// Custom confirmation dialog is shown so default warning should be skipped.
		return;
	}
	return AddLinkDesktopArticleTarget.super.prototype.onBeforeUnload.apply( this, arguments );
};

module.exports = AddLinkDesktopArticleTarget;
