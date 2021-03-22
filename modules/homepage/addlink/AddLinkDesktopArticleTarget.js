var AddLinkArticleTarget = require( 'ext.growthExperiments.AddLink' ).AddLinkArticleTarget,
	AiSuggestionsMode = require( 'ext.growthExperiments.AddLink' ).AiSuggestionsMode;

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
}

OO.inheritClass( AddLinkDesktopArticleTarget, ve.init.mw.DesktopArticleTarget );
OO.mixinClass( AddLinkDesktopArticleTarget, AddLinkArticleTarget );

AddLinkDesktopArticleTarget.static.toolbarGroups = [];

AddLinkDesktopArticleTarget.static.actionGroups =
	AiSuggestionsMode.getActionGroups( AddLinkDesktopArticleTarget.static.actionGroups );

AddLinkDesktopArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response );
	AddLinkDesktopArticleTarget.super.prototype.loadSuccess.call( this, response );
};

AddLinkDesktopArticleTarget.prototype.surfaceReady = function () {
	this.beforeSurfaceReady();
	AddLinkDesktopArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	this.afterSurfaceReady();
};

AddLinkDesktopArticleTarget.prototype.setupToolbar = function () {
	AddLinkDesktopArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	if ( AiSuggestionsMode.toolbarHasTitleElement( this.toolbar.$element ) ) {
		this.toolbar.$element.find( '.oo-ui-toolbar-bar' ).first().prepend(
			AiSuggestionsMode.getTitleElement( { includeIcon: true } )
		);
	}
};

module.exports = AddLinkDesktopArticleTarget;
