( function () {
	if ( !mw.config.get( 'wgGEHelpPanelEnabled' ) ) {
		return;
	}

	// If VisualEditor is available, add the HelpPanel module as a plugin
	// This loads it alongside VE's modules when VE is activated
	if ( mw.loader.getState( 'ext.visualEditor.desktopArticleTarget.init' ) ) {
		mw.loader.using( 'ext.visualEditor.desktopArticleTarget.init' ).done( function () {
			mw.libs.ve.addPlugin( 'ext.growthExperiments.HelpPanel' );
		} );
	}
}() );
