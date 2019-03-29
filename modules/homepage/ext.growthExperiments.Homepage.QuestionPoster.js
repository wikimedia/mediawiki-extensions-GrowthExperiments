( function () {
	var attachButton = function ( config ) {
		var QuestionPosterDialog = require( './ext.growthExperiments.QuestionPosterDialog.js' ),
			Help = require( 'ext.growthExperiments.Help' ),
			logger = new Help.HelpPanelLogger(
				mw.config.get( 'wgGEHomepageLoggingEnabled' ),
				{
					editorInterface: config.editorInterface,
					sessionId: mw.config.get( 'wgGEHomepagePageviewToken' )
				}
			),
			windowManager = new OO.ui.WindowManager( { modal: true } ),
			ctaButton = OO.ui.ButtonWidget.static.infuse( $( config.buttonSelector ) ),
			dialog = new QuestionPosterDialog( $.extend( {
				size: 'medium',
				logger: logger
			}, config.dialog ) );

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
