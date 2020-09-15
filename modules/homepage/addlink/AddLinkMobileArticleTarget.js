var AddLinkArticleTarget = require( 'ext.growthExperiments.AddLink' ).AddLinkArticleTarget;

/**
 * Mobile version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkMobileArticleTarget
 * @extends ve.init.mw.MobileArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddLinkMobileArticleTarget() {
	AddLinkMobileArticleTarget.super.apply( this, arguments );
}

OO.inheritClass( AddLinkMobileArticleTarget, ve.init.mw.MobileArticleTarget );
OO.mixinClass( AddLinkMobileArticleTarget, AddLinkArticleTarget );

AddLinkMobileArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response ).done( function () {
		AddLinkMobileArticleTarget.super.prototype.loadSuccess.call( this, response );
	}.bind( this ) );
};

AddLinkMobileArticleTarget.prototype.surfaceReady = function () {
	this.beforeSurfaceReady();
	AddLinkMobileArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	this.afterSurfaceReady();
};

module.exports = AddLinkMobileArticleTarget;
