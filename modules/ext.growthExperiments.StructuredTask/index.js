module.exports = {
	EditModeMachineSuggestions: require( './EditModeMachineSuggestions.js' ),
	EditModeVisualWithSuggestions: require( './EditModeVisualWithSuggestions.js' ),
	EditModeConfirmationDialog: require( './EditModeConfirmationDialog.js' ),
	SuggestionsArticleTarget: require( './SuggestionsArticleTarget.js' ),
	StructuredTaskArticleTarget: require( './StructuredTaskArticleTarget.js' ),
	StructuredTaskSaveDialog: require( './StructuredTaskSaveDialog.js' ),
	MachineSuggestionsMode: require( './MachineSuggestionsMode.js' ),

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

	AddImageTargetInitializer: require( './addimage/AddImageTargetInitializer.js' ),
	AddImageArticleTarget: require( './addimage/AddImageArticleTarget.js' ),
	RecommendedImageToolbarDialog: require( './addimage/RecommendedImageToolbarDialog.js' ),
	AddImageSaveDialog: require( './addimage/AddImageSaveDialog.js' )
};
