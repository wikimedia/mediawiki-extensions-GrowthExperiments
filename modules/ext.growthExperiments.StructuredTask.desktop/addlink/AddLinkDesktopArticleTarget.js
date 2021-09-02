var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskDesktopArticleTarget = require( '../StructuredTaskDesktopArticleTarget.js' ),
	AddLinkArticleTarget = StructuredTask.AddLinkArticleTarget,
	LinkSuggestionInteractionLogger = StructuredTask.LinkSuggestionInteractionLogger;

/**
 * Desktop version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkDesktopArticleTarget
 * @extends mw.libs.ge.StructuredTaskDesktopArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddLinkDesktopArticleTarget() {
	AddLinkDesktopArticleTarget.super.apply( this, arguments );
	this.$element.addClass( 've-init-mw-addLinkArticleTarget' );
	this.logger = new LinkSuggestionInteractionLogger( {
		// eslint-disable-next-line camelcase
		is_mobile: false
	} );
}

OO.inheritClass( AddLinkDesktopArticleTarget, StructuredTaskDesktopArticleTarget );
OO.mixinClass( AddLinkDesktopArticleTarget, AddLinkArticleTarget );

AddLinkDesktopArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response );
	AddLinkDesktopArticleTarget.super.prototype.loadSuccess.call( this, response );
};

AddLinkDesktopArticleTarget.prototype.surfaceReady = function () {
	this.beforeSurfaceReady();
	AddLinkDesktopArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	this.afterSurfaceReady();
};

module.exports = AddLinkDesktopArticleTarget;
