module.exports = ( function () {
	var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
		AddLinkTargetInitializer = StructuredTask.AddLinkTargetInitializer,
		AddImageTargetInitializer = StructuredTask.AddImageTargetInitializer,
		MachineSuggestionsPlaceholderTool = require( './MachineSuggestionsPlaceholderTool.js' ),
		SuggestionsMobileArticleTarget = require( './SuggestionsMobileArticleTarget.js' );

	/**
	 * Initialize VE classes for structured task
	 * Pass mobile-specific classes to the initializer for the specified task type
	 *
	 * @param {string} taskTypeId Structured task type to set up for
	 */
	function initializeTarget( taskTypeId ) {
		if ( taskTypeId === 'link-recommendation' ) {
			var addLinkTargetInitializer = new AddLinkTargetInitializer( {
				tools: [ MachineSuggestionsPlaceholderTool ],
				windows: [
					require( './addlink/AddLinkMobileSaveDialog.js' ),
					require( './addlink/RecommendedLinkToolbarDialogMobile.js' )
				],
				taskArticleTarget: require( './addlink/AddLinkMobileArticleTarget.js' ),
				suggestionsArticleTarget: SuggestionsMobileArticleTarget
			} );
			addLinkTargetInitializer.disableDefaultEditModeToolsForRegularVeMode();
			addLinkTargetInitializer.initialize();
		} else if ( taskTypeId === 'image-recommendation' ) {
			var addImageTargetInitializer = new AddImageTargetInitializer( {
				tools: [ MachineSuggestionsPlaceholderTool ],
				windows: [
					require( './addimage/AddImageMobileSaveDialog.js' ),
					StructuredTask.RecommendedImageToolbarDialog
				],
				taskArticleTarget: require( './addimage/AddImageMobileArticleTarget.js' ),
				suggestionsArticleTarget: SuggestionsMobileArticleTarget
			} );
			addImageTargetInitializer.disableDefaultEditModeToolsForRegularVeMode();
			addImageTargetInitializer.initialize();
		}
	}

	return {
		initializeTarget: initializeTarget
	};

}() );
