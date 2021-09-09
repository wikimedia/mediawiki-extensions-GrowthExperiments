var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskDesktopArticleTarget = require( '../StructuredTaskDesktopArticleTarget.js' ),
	AddImageArticleTarget = StructuredTask.AddImageArticleTarget;

/**
 * Desktop version of AddImageArticleTarget
 *
 * @class mw.libs.ge.AddImageDesktopArticleTarget
 * @extends mw.libs.ge.StructuredTaskDesktopArticleTarget
 * @mixes mw.libs.ge.AddImageArticleTarget
 * @constructor
 */
function AddImageDesktopArticleTarget() {
	AddImageDesktopArticleTarget.super.apply( this, arguments );
	this.$element.addClass( 've-init-mw-addImageArticleTarget' );
	// TODO: Set this.logger
}

OO.inheritClass( AddImageDesktopArticleTarget, StructuredTaskDesktopArticleTarget );
OO.mixinClass( AddImageDesktopArticleTarget, AddImageArticleTarget );

module.exports = AddImageDesktopArticleTarget;
