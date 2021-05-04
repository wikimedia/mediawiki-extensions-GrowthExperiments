var AddLinkArticleTarget = require( 'ext.growthExperiments.AddLink' ).AddLinkArticleTarget,
	MachineSuggestionsMode = require( 'ext.growthExperiments.AddLink' ).MachineSuggestionsMode;

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
	this.toolbarScrollOffset = 100;
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
	/**
	 * The page is focused in ve.init.mw.DesktopArticleTarget's afterActivate hook.
	 * If the RecommendedLinkContextItem were opened before this focus, it will be torn down.
	 * The focus is happening in the next run loop (see ve.ce.Surface.prototype.focus)
	 * so afterSurfaceReady should be called in the next run loop to make sure that
	 * the annotation is selected after the page is focused.
	 */
	setTimeout( this.afterSurfaceReady.bind( this ) );
};

AddLinkDesktopArticleTarget.prototype.setupToolbar = function () {
	AddLinkDesktopArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	if ( MachineSuggestionsMode.toolbarHasTitleElement( this.toolbar.$element ) ) {
		this.toolbar.$element.find( '.oo-ui-toolbar-bar' ).first().prepend(
			MachineSuggestionsMode.getTitleElement( { includeIcon: true } )
		);
	}
};

module.exports = AddLinkDesktopArticleTarget;
