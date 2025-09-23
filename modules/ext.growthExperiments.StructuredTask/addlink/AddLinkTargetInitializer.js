const TargetInitializer = require( '../TargetInitializer.js' ),
	DMRecommendedLinkAnnotation = require( './dmRecommendedLinkAnnotation.js' ),
	DMRecommendedLinkErrorAnnotation = require( './dmRecommendedLinkErrorAnnotation.js' ),
	CERecommendedLinkAnnotation = require( './ceRecommendedLinkAnnotation.js' ),
	CERecommendedLinkErrorAnnotation = require( './ceRecommendedLinkErrorAnnotation.js' ),
	RecommendedLinkRejectionDialog = require( './RecommendedLinkRejectionDialog.js' ),
	LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' ),
	SuggestionInteractionLogger = require( '../SuggestionInteractionLogger.js' );

/**
 * Handle registrations and de-registrations of VE classes for Add Link structured task
 *
 * @class mw.libs.ge.AddLinkTargetInitializer
 * @extends mw.libs.ge.TargetInitializer
 *
 * @constructor
 *
 * @param {Object} platformConfig Platform-specific configurations
 * @param {mw.libs.ge.AddLinkDesktopArticleTarget|mw.libs.ge.AddLinkMobileArticleTarget} platformConfig.taskArticleTarget
 * @param {mw.libs.ge.SuggestionsDesktopArticleTarget|mw.libs.ge.SuggestionsMobileArticleTarget} platformConfig.suggestionsArticleTarget
 * @param {OO.ui.Window[]} [platformConfig.windows]
 * @param {ve.ui.Tool[]} [platformConfig.tools]
 */
function AddLinkTargetInitializer( platformConfig ) {
	const config = Object.assign( {}, platformConfig );
	config.safeCommands = [ 'recommendedLink' ];
	config.dataModels = [ DMRecommendedLinkAnnotation, DMRecommendedLinkErrorAnnotation ];
	config.annotationViews = [ CERecommendedLinkAnnotation, CERecommendedLinkErrorAnnotation ];
	config.windows = ( platformConfig.windows || [] ).concat( [ RecommendedLinkRejectionDialog ] );
	config.commands = [
		new ve.ui.Command(
			'recommendedLink', 'window', 'toggle', { args: [ 'recommendedLink' ] },
		),
	];
	SuggestionInteractionLogger.initialize( new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'machinesuggestions_mode',
		/* eslint-enable camelcase */
	} ) );
	AddLinkTargetInitializer.super.call( this, config );
}

OO.inheritClass( AddLinkTargetInitializer, TargetInitializer );

module.exports = AddLinkTargetInitializer;
