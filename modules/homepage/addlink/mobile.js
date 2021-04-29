var AddLinkMobileArticleTarget = require( './AddLinkMobileArticleTarget.js' ),
	addlinkClasses = require( 'ext.growthExperiments.AddLink' ),
	AddLinkMobileSaveDialog = require( './AddLinkMobileSaveDialog.js' ),
	MachineSuggestionsPlaceholderTool = require( './MachineSuggestionsPlaceholderTool.js' ),
	RecommendedLinkToolbarDialogMobile = require( './RecommendedLinkToolbarDialogMobile.js' );

ve.dm.modelRegistry.register( addlinkClasses.DMRecommendedLinkAnnotation );
ve.ce.annotationFactory.register( addlinkClasses.CERecommendedLinkAnnotation );
ve.dm.modelRegistry.register( addlinkClasses.DMRecommendedLinkErrorAnnotation );
ve.ce.annotationFactory.register( addlinkClasses.CERecommendedLinkErrorAnnotation );
ve.ui.windowFactory.register( addlinkClasses.RecommendedLinkRejectionDialog );
ve.ui.windowFactory.register( AddLinkMobileSaveDialog );
ve.ui.toolFactory.register( MachineSuggestionsPlaceholderTool );

ve.ui.windowFactory.register( RecommendedLinkToolbarDialogMobile );
ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'recommendedLink', 'window', 'toggle', { args: [ 'recommendedLink' ] }
	)
);

// T280129 Disable all unnecessary context items
Object.keys( ve.ui.contextItemFactory.registry ).forEach( function ( contextItem ) {
	ve.ui.contextItemFactory.unregister( contextItem );
} );

// T280129 Disable all unnecessary tools
Object.keys( ve.ui.toolFactory.registry ).forEach( function ( toolFactoryItem ) {
	var safeList = [ 'machineSuggestionsSave', 'machineSuggestionsPlaceholder', 'showMobileSave', 'showSave', 'back' ];
	if ( safeList.indexOf( toolFactoryItem ) === -1 ) {
		ve.ui.toolFactory.unregister( toolFactoryItem );
	}
} );

// T281434 Disable window related commands (including their keyboard shortcuts)
Object.keys( ve.ui.commandRegistry.registry ).forEach( function ( commandItem ) {
	var safeList = [ 'showSave', 'showChanges', 'recommendedLink', 'back' ];
	if ( safeList.indexOf( commandItem ) === -1 ) {
		ve.ui.commandRegistry.unregister( commandItem );
	}
} );

// Disable context items for non-recommended links
ve.ce.MWInternalLinkAnnotation.static.canBeActive = false;

// HACK: Override the registration of MobileArticleTarget for 'wikitext'
ve.init.mw.targetFactory.register( AddLinkMobileArticleTarget );
