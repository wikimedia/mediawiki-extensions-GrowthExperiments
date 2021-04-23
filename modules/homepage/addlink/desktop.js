var AddLinkDesktopArticleTarget = require( './AddLinkDesktopArticleTarget.js' ),
	addlinkClasses = require( 'ext.growthExperiments.AddLink' ),
	AddLinkSaveDialog = require( './AddLinkSaveDialog.js' ),
	MachineSuggestionsSaveTool = require( './MachineSuggestionsSaveTool.js' ),
	RecommendedLinkToolbarDialogDesktop = require( './RecommendedLinkToolbarDialogDesktop.js' );

ve.dm.modelRegistry.register( addlinkClasses.DMRecommendedLinkAnnotation );
ve.ce.annotationFactory.register( addlinkClasses.CERecommendedLinkAnnotation );
ve.dm.modelRegistry.register( addlinkClasses.DMRecommendedLinkErrorAnnotation );
ve.ce.annotationFactory.register( addlinkClasses.CERecommendedLinkErrorAnnotation );
ve.ui.windowFactory.register( addlinkClasses.RecommendedLinkRejectionDialog );
ve.ui.windowFactory.register( AddLinkSaveDialog );
ve.ui.toolFactory.register( MachineSuggestionsSaveTool );

ve.ui.windowFactory.register( RecommendedLinkToolbarDialogDesktop );
ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'recommendedLink', 'window', 'toggle', { args: [ 'recommendedLink' ] }
	)
);

// Disable context items for non-recommended links
ve.ce.MWInternalLinkAnnotation.static.canBeActive = false;
ve.ui.contextItemFactory.unregister( 'link' );
ve.ui.contextItemFactory.unregister( 'link/internal' );
ve.ui.toolFactory.unregister( ve.ui.MWLinkInspectorTool );

// HACK: Override the registration of DesktopArticleTarget for 'wikitext'
ve.init.mw.targetFactory.register( AddLinkDesktopArticleTarget );
