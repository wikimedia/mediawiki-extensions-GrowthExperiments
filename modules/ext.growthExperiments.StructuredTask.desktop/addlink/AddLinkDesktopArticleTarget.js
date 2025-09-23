const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskDesktopArticleTarget = require( '../StructuredTaskDesktopArticleTarget.js' ),
	AddLinkArticleTarget = StructuredTask.addLink().AddLinkArticleTarget,
	LinkSuggestionInteractionLogger = StructuredTask.addLink().LinkSuggestionInteractionLogger;

/**
 * Desktop version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkDesktopArticleTarget
 * @extends mw.libs.ge.StructuredTaskDesktopArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddLinkDesktopArticleTarget() {
	const logger = new LinkSuggestionInteractionLogger( {
		// eslint-disable-next-line camelcase
		is_mobile: false,
	} );
	AddLinkDesktopArticleTarget.super.apply( this, arguments );
	AddLinkArticleTarget.call( this, logger );

	this.$element.addClass( 've-init-mw-addLinkArticleTarget' );
	this.logger = logger;
	this.connect( this, { save: 'onSaveComplete' } );
}

OO.inheritClass( AddLinkDesktopArticleTarget, StructuredTaskDesktopArticleTarget );
OO.mixinClass( AddLinkDesktopArticleTarget, AddLinkArticleTarget );

module.exports = AddLinkDesktopArticleTarget;
