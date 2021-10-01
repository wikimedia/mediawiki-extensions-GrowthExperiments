var TargetInitializer = require( '../TargetInitializer.js' ),
	RecommendedImageRejectionDialog = require( './RecommendedImageRejectionDialog.js' ),
	RecommendedImageViewer = require( './RecommendedImageViewer.js' ),
	ImageSuggestionInteractionLogger = require( './ImageSuggestionInteractionLogger.js' ),
	SuggestionInteractionLogger = require( '../SuggestionInteractionLogger.js' );

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
	var config = $.extend( {}, platformConfig ),
		toolbarDialogCommand = new ve.ui.Command(
			'recommendedImage', 'window', 'toggle', { args: [ 'recommendedImage' ] }
		);
	config.safeCommands = [ toolbarDialogCommand.name ];
	config.dataModels = [];
	config.annotationViews = [];
	config.windows = ( platformConfig.windows || [] ).concat( [
		RecommendedImageRejectionDialog, RecommendedImageViewer
	] );
	config.commands = [ toolbarDialogCommand ];
	SuggestionInteractionLogger.initialize( new ImageSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'machinesuggestions_mode'
		/* eslint-enable camelcase */
	} ) );
	AddImageTargetInitializer.super.call( this, config );
}

OO.inheritClass( AddImageTargetInitializer, TargetInitializer );

module.exports = AddImageTargetInitializer;
