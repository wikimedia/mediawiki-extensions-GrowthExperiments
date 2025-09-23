const TargetInitializer = require( '../TargetInitializer.js' ),
	RecommendedSectionImageRejectionDialog = require( './RecommendedSectionImageRejectionDialog.js' ),
	RecommendedImageViewer = require( '../addimage/RecommendedImageViewer.js' ),
	AddImageDetailsDialog = require( '../addimage/AddImageDetailsDialog.js' ),
	ImageSuggestionInteractionLogger = require( '../addimage/ImageSuggestionInteractionLogger.js' ),
	SuggestionInteractionLogger = require( '../SuggestionInteractionLogger.js' ),
	CERecommendedImageNode = require( '../addimage/ceRecommendedImageNode.js' ),
	DMRecommendedImageNode = require( '../addimage/dmRecommendedImageNode.js' ),
	CERecommendedImageCaptionNode = require( '../addimage/ceRecommendedImageCaptionNode.js' ),
	DMRecommendedImageCaptionNode = require( '../addimage/dmRecommendedImageCaptionNode.js' ),
	CERecommendedImagePlaceholderNode = require( './ceRecommendedImagePlaceholderNode.js' ),
	DMRecommendedImagePlaceholderNode = require( './dmRecommendedImagePlaceholderNode.js' ),
	AddImageLinearDeleteKeyDownHandler = require( '../addimage/AddImageLinearDeleteKeyDownHandler.js' ),
	AddSectionImageCaptionInfoDialog = require( './AddSectionImageCaptionInfoDialog.js' );

/**
 * Handle registrations and de-registrations of VE classes for Add Image structured task
 *
 * @class mw.libs.ge.AddSectionImageTargetInitializer
 * @extends mw.libs.ge.TargetInitializer
 *
 * @constructor
 *
 * @param {Object} platformConfig Platform-specific configurations
 * @param {mw.libs.ge.AddSectionImageDesktopArticleTarget|mw.libs.ge.AddSectionImageMobileArticleTarget} platformConfig.taskArticleTarget
 * @param {mw.libs.ge.SuggestionsDesktopArticleTarget|mw.libs.ge.SuggestionsMobileArticleTarget} platformConfig.suggestionsArticleTarget
 * @param {OO.ui.Window[]} [platformConfig.windows]
 * @param {ve.ui.Tool[]} [platformConfig.tools]
 */
function AddSectionImageTargetInitializer( platformConfig ) {
	const config = Object.assign( {}, platformConfig ),
		toolbarDialogCommand = new ve.ui.Command(
			'recommendedImage', 'window', 'toggle', { args: [ 'recommendedImage' ] },
		);
	// selectAll command keeps the focus on the field when selecting all
	// This is used in the caption step.
	config.safeCommands = [ toolbarDialogCommand.name, 'selectAll' ];
	config.dataModels = [ DMRecommendedImageNode, DMRecommendedImageCaptionNode, DMRecommendedImagePlaceholderNode ];
	config.annotationViews = [];
	config.windows = ( platformConfig.windows || [] ).concat( [
		RecommendedSectionImageRejectionDialog,
		RecommendedImageViewer,
		AddImageDetailsDialog,
		AddSectionImageCaptionInfoDialog,
	] );
	config.commands = [ toolbarDialogCommand ];
	config.nodes = [ CERecommendedImageNode, CERecommendedImageCaptionNode, CERecommendedImagePlaceholderNode ];
	SuggestionInteractionLogger.initialize( new ImageSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'machinesuggestions_mode',
		/* eslint-enable camelcase */
	} ) );
	// Desktop doesn't have a back button in the toolbar.
	config.shouldOverrideBackTool = OO.ui.isMobile();
	config.keyDownHandlers = [ AddImageLinearDeleteKeyDownHandler ];
	AddSectionImageTargetInitializer.super.call( this, config );
}

OO.inheritClass( AddSectionImageTargetInitializer, TargetInitializer );

module.exports = AddSectionImageTargetInitializer;
