( function () {
	const veState = mw.loader.getState( 'ext.visualEditor.desktopArticleTarget.init' ),
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

	// If there is an active suggested edit session, load the help panel
	// module, which will result in either the call to action or
	// the open help panel process dialog displaying.
	if ( suggestedEditSession && suggestedEditSession.active ) {
		mw.loader.load( 'ext.growthExperiments.HelpPanel' );
	}

	// If VisualEditor is available, add the HelpPanel module as a plugin
	// This loads it alongside VE's modules when VE is activated, but doesn't load VE's init module
	// if it wasn't already going to be loaded
	if ( veState === 'loading' || veState === 'loaded' || veState === 'ready' ) {
		mw.loader.using( 'ext.visualEditor.desktopArticleTarget.init' ).then( () => {
			mw.libs.ve.addPlugin( 'ext.growthExperiments.HelpPanel' );
		} );
	}

	// MobileFrontend's editor doesn't have a similar plugin system, so instead load the HelpPanel
	// module separately when the editor begins loading
	mw.hook( 'mobileFrontend.editorOpening' ).add( () => {
		mw.loader.load( 'ext.growthExperiments.HelpPanel' );
	} );
}() );
