( function () {
	var HelpDeskDialog = require( './ext.growthExperiments.HelpDeskDialog.js' ),
		Help = require( 'ext.growthExperiments.Help' ),
		HelpPanelLogger = Help.HelpPanelLogger,
		lifecycle,
		logger = new HelpPanelLogger( Help.configData.GEHelpPanelLoggingEnabled ),
		windowManager = new OO.ui.WindowManager( { modal: true } ),
		$helpModule = $( '.growthexperiments-homepage-module-help' ),
		$buttonToInfuse = $( '#mw-ge-homepage-help-cta' ),
		homepageHelpDialog,
		helpCtaButton = OO.ui.ButtonWidget.static.infuse( $buttonToInfuse );

	homepageHelpDialog = new HelpDeskDialog( {
		size: 'medium',
		logger: logger
	} );

	$helpModule.append( windowManager.$element );
	windowManager.addWindows( [ homepageHelpDialog ] );

	helpCtaButton.on( 'click', function () {
		lifecycle = windowManager.openWindow( homepageHelpDialog );
		homepageHelpDialog.executeAction( 'questionreview' );
		lifecycle.closing.done( function () {
			logger.log( 'close' );
		} );
	} );

}() );
