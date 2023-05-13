module.exports = ( function () {
	var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
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
			var AddLinkTargetInitializer = StructuredTask.addLink().AddLinkTargetInitializer,
				addLinkTargetInitializer = new AddLinkTargetInitializer( {
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
			var AddImageTargetInitializer = StructuredTask.addImage().AddImageTargetInitializer,
				addImageTargetInitializer = new AddImageTargetInitializer( {
					tools: [ MachineSuggestionsSaveTool ],
					windows: [
						require( './addimage/AddImageDesktopSaveDialog.js' ),
						StructuredTask.addImage().RecommendedImageToolbarDialog
					],
					taskArticleTarget: require( './addimage/AddImageDesktopArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsDesktopArticleTarget
				} );
			addImageTargetInitializer.initialize();
		} else if ( taskTypeId === 'section-image-recommendation' ) {
			var AddSectionImageTargetInitializer = StructuredTask.addSectionImage().AddSectionImageTargetInitializer,
				addSectionImageTargetInitializer = new AddSectionImageTargetInitializer( {
					tools: [ MachineSuggestionsSaveTool ],
					windows: [
						require( './addimage/AddImageDesktopSaveDialog.js' ),
						StructuredTask.addImage().RecommendedImageToolbarDialog
					],
					taskArticleTarget: require( './addsectionimage/AddSectionImageDesktopArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsDesktopArticleTarget
				} );
			addSectionImageTargetInitializer.initialize();
		}
	}

	return {
		initializeTarget: initializeTarget
	};

}() );
