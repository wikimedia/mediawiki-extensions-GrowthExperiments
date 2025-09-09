module.exports = ( function () {
	const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
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
			const AddLinkTargetInitializer = StructuredTask.addLink().AddLinkTargetInitializer,
				addLinkTargetInitializer = new AddLinkTargetInitializer( {
					tools: [ MachineSuggestionsSaveTool ],
					windows: [
						require( './addlink/AddLinkDesktopSaveDialog.js' ),
						require( './addlink/RecommendedLinkToolbarDialogDesktop.js' ),
					],
					taskArticleTarget: require( './addlink/AddLinkDesktopArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsDesktopArticleTarget,
				} );
			addLinkTargetInitializer.initialize();
		} else if ( taskTypeId === 'image-recommendation' ) {
			const AddImageTargetInitializer = StructuredTask.addImage().AddImageTargetInitializer,
				addImageTargetInitializer = new AddImageTargetInitializer( {
					tools: [ MachineSuggestionsSaveTool ],
					windows: [
						require( './addimage/AddImageDesktopSaveDialog.js' ),
						StructuredTask.addImage().RecommendedImageToolbarDialog,
					],
					taskArticleTarget: require( './addimage/AddImageDesktopArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsDesktopArticleTarget,
				} );
			addImageTargetInitializer.initialize();
		} else if ( taskTypeId === 'section-image-recommendation' ) {
			const AddSectionImageTargetInitializer = StructuredTask.addSectionImage().AddSectionImageTargetInitializer,
				addSectionImageTargetInitializer = new AddSectionImageTargetInitializer( {
					tools: [ MachineSuggestionsSaveTool ],
					windows: [
						require( './addimage/AddImageDesktopSaveDialog.js' ),
						StructuredTask.addSectionImage().RecommendedSectionImageToolbarDialog,
					],
					taskArticleTarget: require( './addsectionimage/AddSectionImageDesktopArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsDesktopArticleTarget,
				} );
			addSectionImageTargetInitializer.initialize();
		} else if ( taskTypeId === 'revise-tone' ) {
			const reviseToneInitializer = new ( StructuredTask.reviseTone().ReviseToneInitializer )();
			reviseToneInitializer.initialize();
		}
	}

	return {
		initializeTarget: initializeTarget,
	};

}() );
