const TargetInitializer = require( '../TargetInitializer.js' ),
	RecommendedImageRejectionDialog = require( './RecommendedImageRejectionDialog.js' ),
	RecommendedImageViewer = require( './RecommendedImageViewer.js' ),
	AddImageDetailsDialog = require( './AddImageDetailsDialog.js' ),
	ImageSuggestionInteractionLogger = require( './ImageSuggestionInteractionLogger.js' ),
	SuggestionInteractionLogger = require( '../SuggestionInteractionLogger.js' ),
	CERecommendedImageNode = require( './ceRecommendedImageNode.js' ),
	DMRecommendedImageNode = require( './dmRecommendedImageNode.js' ),
	CERecommendedImageCaptionNode = require( './ceRecommendedImageCaptionNode.js' ),
	DMRecommendedImageCaptionNode = require( './dmRecommendedImageCaptionNode.js' ),
	AddImageLinearDeleteKeyDownHandler = require( './AddImageLinearDeleteKeyDownHandler.js' ),
	AddImageCaptionInfoDialog = require( './AddImageCaptionInfoDialog.js' );

/**
 * Handle registrations and de-registrations of VE classes for Add Image structured task
 *
 * @class mw.libs.ge.AddImageTargetInitializer
 * @extends mw.libs.ge.TargetInitializer
 *
 * @constructor
 *
 * @param {Object} platformConfig Platform-specific configurations
 * @param {mw.libs.ge.AddImageDesktopArticleTarget|mw.libs.ge.AddImageMobileArticleTarget} platformConfig.taskArticleTarget
 * @param {mw.libs.ge.SuggestionsDesktopArticleTarget|mw.libs.ge.SuggestionsMobileArticleTarget} platformConfig.suggestionsArticleTarget
 * @param {OO.ui.Window[]} [platformConfig.windows]
 * @param {ve.ui.Tool[]} [platformConfig.tools]
 */
function AddImageTargetInitializer( platformConfig ) {
	const config = Object.assign( {}, platformConfig ),
		toolbarDialogCommand = new ve.ui.Command(
			'recommendedImage', 'window', 'toggle', { args: [ 'recommendedImage' ] },
		);
	// selectAll command keeps the focus on the field when selecting all
	// This is used in the caption step.
	config.safeCommands = [ toolbarDialogCommand.name, 'selectAll' ];
	config.dataModels = [ DMRecommendedImageNode, DMRecommendedImageCaptionNode ];
	config.annotationViews = [];
	config.windows = ( platformConfig.windows || [] ).concat( [
		RecommendedImageRejectionDialog,
		RecommendedImageViewer,
		AddImageDetailsDialog,
		AddImageCaptionInfoDialog,
	] );
	config.commands = [ toolbarDialogCommand ];
	config.nodes = [ CERecommendedImageNode, CERecommendedImageCaptionNode ];
	SuggestionInteractionLogger.initialize( new ImageSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'machinesuggestions_mode',
		/* eslint-enable camelcase */
	} ) );
	// Desktop doesn't have a back button in the toolbar.
	config.shouldOverrideBackTool = OO.ui.isMobile();
	config.keyDownHandlers = [ AddImageLinearDeleteKeyDownHandler ];
	AddImageTargetInitializer.super.call( this, config );
}

OO.inheritClass( AddImageTargetInitializer, TargetInitializer );

module.exports = AddImageTargetInitializer;
