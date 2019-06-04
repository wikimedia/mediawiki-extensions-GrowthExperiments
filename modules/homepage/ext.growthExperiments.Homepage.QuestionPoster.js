( function () {
	var attachButton = function ( config ) {
		var QuestionPosterDialog, Help, logger, windowManager, ctaButton, dialog;
		// no-op if the CTA button isn't found. This happens if the RL module is loaded
		// before the corresponding HTML is set in the DOM, as currently occurs with
		// the mobile homepage modules.
		if ( !$( config.buttonSelector ).length ) {
			return;
		}
		QuestionPosterDialog = require( './ext.growthExperiments.QuestionPosterDialog.js' );
		Help = require( 'ext.growthExperiments.Help' );
		logger = new Help.HelpPanelLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			{
				editorInterface: config.editorInterface,
				sessionId: mw.config.get( 'wgGEHomepagePageviewToken' )
			}
		);
		windowManager = new OO.ui.WindowManager( { modal: true } );
		dialog = new QuestionPosterDialog( $.extend( {
			size: 'medium',
			logger: logger
		}, config.dialog ) );
		ctaButton = OO.ui.ButtonWidget.static.infuse( $( config.buttonSelector ) );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		ctaButton.on( 'click', function () {
			var lifecycle = windowManager.openWindow( dialog );
			dialog.executeAction( 'questionreview' );
			lifecycle.closing.done( function () {
				logger.log( 'close' );
			} );
		} );
	};
	module.exports = attachButton;
}() );
