let addLinkModules, addImageModules, addSectionImageModules;

function addLink() {
	if ( !addLinkModules ) {
		addLinkModules = {
			AddLinkTargetInitializer: require( './addlink/AddLinkTargetInitializer.js' ),
			AddLinkArticleTarget: require( './addlink/AddLinkArticleTarget.js' ),
			DMRecommendedLinkAnnotation: require( './addlink/dmRecommendedLinkAnnotation.js' ),
			CERecommendedLinkAnnotation: require( './addlink/ceRecommendedLinkAnnotation.js' ),
			DMRecommendedLinkErrorAnnotation: require( './addlink/dmRecommendedLinkErrorAnnotation.js' ),
			CERecommendedLinkErrorAnnotation: require( './addlink/ceRecommendedLinkErrorAnnotation.js' ),
			RecommendedLinkToolbarDialog: require( './addlink/RecommendedLinkToolbarDialog.js' ),
			RecommendedLinkRejectionDialog: require( './addlink/RecommendedLinkRejectionDialog.js' ),
			AddLinkSaveDialog: require( './addlink/AddLinkSaveDialog.js' ),
			LinkSuggestionInteractionLogger: require( './addlink/LinkSuggestionInteractionLogger.js' ),
		};
	}
	return addLinkModules;
}

function addImage() {
	if ( !addImageModules ) {
		addImageModules = {
			AddImageTargetInitializer: require( './addimage/AddImageTargetInitializer.js' ),
			AddImageArticleTarget: require( './addimage/AddImageArticleTarget.js' ),
			RecommendedImageToolbarDialog: require( './addimage/RecommendedImageToolbarDialog.js' ),
			AddImageSaveDialog: require( './addimage/AddImageSaveDialog.js' ),
			ImageSuggestionInteractionLogger: require( './addimage/ImageSuggestionInteractionLogger.js' ),
			AddImageUtils: require( './addimage/AddImageUtils.js' ),
		};
	}
	return addImageModules;
}

function addSectionImage() {
	if ( !addSectionImageModules ) {
		addSectionImageModules = {
			AddSectionImageTargetInitializer: require( './addsectionimage/AddSectionImageTargetInitializer.js' ),
			AddSectionImageArticleTarget: require( './addsectionimage/AddSectionImageArticleTarget.js' ),
			RecommendedSectionImageToolbarDialog: require( './addsectionimage/RecommendedSectionImageToolbarDialog.js' ),
			AddSectionImageSaveDialog: require( './addimage/AddImageSaveDialog.js' ),
			ImageSuggestionInteractionLogger: require( './addimage/ImageSuggestionInteractionLogger.js' ),
			AddImageUtils: require( './addimage/AddImageUtils.js' ),
		};
	}
	return addSectionImageModules;
}

module.exports = {
	EditModeMachineSuggestions: require( './EditModeMachineSuggestions.js' ),
	EditModeVisualWithSuggestions: require( './EditModeVisualWithSuggestions.js' ),
	EditModeConfirmationDialog: require( './EditModeConfirmationDialog.js' ),
	SuggestionsArticleTarget: require( './SuggestionsArticleTarget.js' ),
	StructuredTaskArticleTarget: require( './StructuredTaskArticleTarget.js' ),
	StructuredTaskSaveDialog: require( './StructuredTaskSaveDialog.js' ),
	MachineSuggestionsMode: require( './MachineSuggestionsMode.js' ),

	// Lazy-load files specific to a task type.
	addLink: addLink,
	addImage: addImage,
	addSectionImage: addSectionImage,
};
