module.exports = ( function () {
	var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
		AddLinkTargetInitializer = StructuredTask.AddLinkTargetInitializer,
		AddImageTargetInitializer = StructuredTask.AddImageTargetInitializer,
		MachineSuggestionsSaveTool = require( './MachineSuggestionsSaveTool.js' ),
		SuggestionsDesktopArticleTarget = require( './SuggestionsDesktopArticleTarget.js' );

	/**
	 * Initialize VE classes for structured task
	 * Pass desktop-specific classes to the initializer for the specified task type
	 *
	 * @param {string} taskTypeId Structured task type to set up for
	 */
	function initializeTarget( taskTypeId ) {
		if ( taskTypeId === 'link-recommendation' ) {
			var addLinkTargetInitializer = new AddLinkTargetInitializer( {
				tools: [ MachineSuggestionsSaveTool ],
				windows: [
					require( './addlink/AddLinkDesktopSaveDialog.js' ),
					require( './addlink/RecommendedLinkToolbarDialogDesktop.js' )
				],
				taskArticleTarget: require( './addlink/AddLinkDesktopArticleTarget.js' ),
				suggestionsArticleTarget: SuggestionsDesktopArticleTarget
			} );
			addLinkTargetInitializer.initialize();
		} else if ( taskTypeId === 'image-recommendation' ) {
			var addImageTargetInitializer = new AddImageTargetInitializer( {
				tools: [ MachineSuggestionsSaveTool ],
				windows: [ StructuredTask.RecommendedImageToolbarDialog ],
				taskArticleTarget: require( './addimage/AddImageDesktopArticleTarget.js' ),
				suggestionsArticleTarget: SuggestionsDesktopArticleTarget
			} );
			addImageTargetInitializer.disableDefaultEditModeToolsForRegularVeMode();
			addImageTargetInitializer.initialize();
		}
	}

	return {
		initializeTarget: initializeTarget
	};

}() );
