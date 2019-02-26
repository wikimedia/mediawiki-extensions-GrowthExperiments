( function () {
	var HelpPanel = require( 'ext.growthExperiments.HelpPanel' ),
		HelpPanelLogger = HelpPanel.HelpPanelLogger,
		HelpDeskDialog = require( './ext.growthExperiments.HelpDeskDialog.js' ),
		lifecycle,
		logger = new HelpPanelLogger( HelpPanel.configData.GEHelpPanelLoggingEnabled ),
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
