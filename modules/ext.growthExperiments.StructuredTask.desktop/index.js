module.exports = ( function () {
	var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
		AddLinkTargetInitializer = StructuredTask.AddLinkTargetInitializer,
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
		}
	}

	return {
		initializeTarget: initializeTarget
	};

}() );
