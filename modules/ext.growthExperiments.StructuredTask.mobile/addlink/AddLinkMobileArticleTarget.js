const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskMobileArticleTarget = require( '../StructuredTaskMobileArticleTarget.js' ),
	AddLinkArticleTarget = StructuredTask.addLink().AddLinkArticleTarget,
	LinkSuggestionInteractionLogger = StructuredTask.addLink().LinkSuggestionInteractionLogger;

/**
 * Mobile version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkMobileArticleTarget
 * @extends mw.libs.ge.StructuredTaskMobileArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddLinkMobileArticleTarget() {
	// eslint-disable-next-line camelcase
	const logger = new LinkSuggestionInteractionLogger( { is_mobile: true } );
	AddLinkMobileArticleTarget.super.apply( this, arguments );
	AddLinkArticleTarget.call( this, logger );

	this.$element.addClass( 've-init-mw-addLinkArticleTarget' );
	this.logger = logger;
	this.connect( this, { save: 'onSaveComplete' } );
}

OO.inheritClass( AddLinkMobileArticleTarget, StructuredTaskMobileArticleTarget );
OO.mixinClass( AddLinkMobileArticleTarget, AddLinkArticleTarget );

module.exports = AddLinkMobileArticleTarget;
