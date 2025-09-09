module.exports = ( function () {
	const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
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
			const AddLinkTargetInitializer = StructuredTask.addLink().AddLinkTargetInitializer,
				addLinkTargetInitializer = new AddLinkTargetInitializer( {
					tools: [ MachineSuggestionsPlaceholderTool ],
					windows: [
						require( './addlink/AddLinkMobileSaveDialog.js' ),
						require( './addlink/RecommendedLinkToolbarDialogMobile.js' ),
					],
					taskArticleTarget: require( './addlink/AddLinkMobileArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsMobileArticleTarget,
				} );
			addLinkTargetInitializer.disableDefaultEditModeToolsForRegularVeMode();
			addLinkTargetInitializer.initialize();
		} else if ( taskTypeId === 'image-recommendation' ) {
			const AddImageTargetInitializer = StructuredTask.addImage().AddImageTargetInitializer,
				addImageTargetInitializer = new AddImageTargetInitializer( {
					tools: [ MachineSuggestionsPlaceholderTool ],
					windows: [
						require( './addimage/AddImageMobileSaveDialog.js' ),
						StructuredTask.addImage().RecommendedImageToolbarDialog,
					],
					taskArticleTarget: require( './addimage/AddImageMobileArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsMobileArticleTarget,
				} );
			addImageTargetInitializer.disableDefaultEditModeToolsForRegularVeMode();
			addImageTargetInitializer.initialize();
		} else if ( taskTypeId === 'section-image-recommendation' ) {
			const AddSectionImageTargetInitializer = StructuredTask.addSectionImage().AddSectionImageTargetInitializer,
				addSectionImageTargetInitializer = new AddSectionImageTargetInitializer( {
					tools: [ MachineSuggestionsPlaceholderTool ],
					windows: [
						require( './addimage/AddImageMobileSaveDialog.js' ),
						StructuredTask.addSectionImage().RecommendedSectionImageToolbarDialog,
					],
					taskArticleTarget: require( './addsectionimage/AddSectionImageMobileArticleTarget.js' ),
					suggestionsArticleTarget: SuggestionsMobileArticleTarget,
				} );
			addSectionImageTargetInitializer.disableDefaultEditModeToolsForRegularVeMode();
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
