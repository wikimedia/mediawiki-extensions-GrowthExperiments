var TargetInitializer = require( '../TargetInitializer.js' );

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
	config.windows = platformConfig.windows || [];
	config.commands = [ toolbarDialogCommand ];
	// TODO: initialize SuggestionInteractionLogger
	AddImageTargetInitializer.super.call( this, config );
}

OO.inheritClass( AddImageTargetInitializer, TargetInitializer );

module.exports = AddImageTargetInitializer;
