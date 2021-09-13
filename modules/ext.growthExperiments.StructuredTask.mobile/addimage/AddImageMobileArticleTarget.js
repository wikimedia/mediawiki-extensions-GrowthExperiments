var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskMobileArticleTarget = require( '../StructuredTaskMobileArticleTarget.js' ),
	AddImageArticleTarget = StructuredTask.AddImageArticleTarget;

/**
 * Mobile version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkMobileArticleTarget
 * @extends mw.libs.ge.StructuredTaskMobileArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddImageMobileArticleTarget() {
	AddImageMobileArticleTarget.super.apply( this, arguments );
	this.$element.addClass( 've-init-mw-addImageArticleTarget' );
	// TODO: Set this.logger
}

OO.inheritClass( AddImageMobileArticleTarget, StructuredTaskMobileArticleTarget );
OO.mixinClass( AddImageMobileArticleTarget, AddImageArticleTarget );

module.exports = AddImageMobileArticleTarget;
