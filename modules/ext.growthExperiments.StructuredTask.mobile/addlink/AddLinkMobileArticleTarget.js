var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskMobileArticleTarget = require( '../StructuredTaskMobileArticleTarget.js' ),
	AddLinkArticleTarget = StructuredTask.AddLinkArticleTarget,
	LinkSuggestionInteractionLogger = StructuredTask.LinkSuggestionInteractionLogger;

/**
 * Mobile version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkMobileArticleTarget
 * @extends mw.libs.ge.StructuredTaskMobileArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddLinkMobileArticleTarget() {
	AddLinkMobileArticleTarget.super.apply( this, arguments );
	this.$element.addClass( 've-init-mw-addLinkArticleTarget' );
	// eslint-disable-next-line camelcase
	this.logger = new LinkSuggestionInteractionLogger( { is_mobile: true } );
}

OO.inheritClass( AddLinkMobileArticleTarget, StructuredTaskMobileArticleTarget );
OO.mixinClass( AddLinkMobileArticleTarget, AddLinkArticleTarget );

module.exports = AddLinkMobileArticleTarget;
