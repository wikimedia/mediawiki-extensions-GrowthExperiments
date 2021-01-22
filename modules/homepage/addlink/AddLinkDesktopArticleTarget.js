var AddLinkArticleTarget = require( 'ext.growthExperiments.AddLink' ).AddLinkArticleTarget;

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

AddLinkDesktopArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response ).done( function () {
		AddLinkDesktopArticleTarget.super.prototype.loadSuccess.call( this, response );
	}.bind( this ) );
};

AddLinkDesktopArticleTarget.prototype.surfaceReady = function () {
	this.beforeSurfaceReady();
	AddLinkDesktopArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	this.afterSurfaceReady();
};

module.exports = AddLinkDesktopArticleTarget;
