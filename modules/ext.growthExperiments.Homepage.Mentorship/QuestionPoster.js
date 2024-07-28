( function () {
	var attachButton = function ( config, $container ) {
		var appendWindowManagerToBody = function ( windowManager, dialog ) {
				// eslint-disable-next-line no-jquery/no-global-selector
				$( 'body' ).append( windowManager.$element );
				windowManager.addWindows( [ dialog ] );
			},
			/**
			 * Register the route for the question dialog.
			 *
			 * @param {OO.Router} router
			 * @param {string} route
			 * @param {OO.ui.WindowManager} windowManager
			 * @param {QuestionPosterDialog} dialog
			 * @param {Help.HelpPanelLogger} logger
			 */
			registerDialogRoute = function ( router, route, windowManager, dialog, logger ) {
				router.addRoute( route, () => {
					var lifecycle = windowManager.openWindow( dialog, { panel: 'ask-help' } );
					logger.log( 'ask-help' );
					lifecycle.closing.done( () => {
						if ( router.getPath() === route ) {
							// The user clicked the "close" button on the dialog, go back to
							// previous route.
							router.back();
						}
					} );
				} );
			},
			/**
			 * Close the window manager when the path changes via the back button.
			 *
			 * @param {OO.Router} router
			 * @param {string} route
			 * @param {OO.ui.WindowManager} windowManager
			 * @param {QuestionPosterDialog} dialog
			 */
			closeWindowOnHashChange = function ( router, route, windowManager, dialog ) {
				$( window ).on( 'hashchange', () => {
					if ( router.getPath() !== route ) {
						windowManager.closeWindow( dialog );
					}
				} );
			},
			questionRoute = '/homepage/' + config.dialog.name + '/question',
			suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

		// no-op if the CTA button isn't found. This happens if the RL module is loaded
		// before the corresponding HTML is set in the DOM, as currently occurs with
		// the mobile homepage modules and when user opted out from mentorship.
		if ( !$container.find( config.buttonSelector ).length ) {
			return;
		}

		var routerInstance = require( 'mediawiki.router' );
		var Help = require( 'ext.growthExperiments.Help' );
		var QuestionPosterDialog = Help.HelpPanelProcessDialog;
		var loggerInstance = new Help.HelpPanelLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			{
				context: config.context,
				sessionId: mw.config.get( 'wgGEHomepagePageviewToken' )
			}
		);
		var windowManagerInstance = new OO.ui.WindowManager( { modal: true } );
		suggestedEditSession.helpPanelShouldBeLocked = true;
		var dialogInstance = new QuestionPosterDialog( Object.assign( {
			size: 'medium',
			logger: loggerInstance,
			layoutType: 'dialog',
			questionPosterAllowIncludingTitle: false,
			suggestedEditSession: suggestedEditSession,
			showCogMenu: false,
			askSource: 'mentor-homepage',
			isQuestionPoster: true
		}, config.dialog ) );
		var ctaButton = OO.ui.ButtonWidget.static.infuse( $container.find( config.buttonSelector ) );

		appendWindowManagerToBody( windowManagerInstance, dialogInstance );
		registerDialogRoute(
			routerInstance,
			questionRoute,
			windowManagerInstance,
			dialogInstance,
			loggerInstance
		);
		closeWindowOnHashChange(
			routerInstance,
			questionRoute,
			windowManagerInstance,
			dialogInstance
		);
		ctaButton.on( 'click', () => {
			routerInstance.navigate( '#' + questionRoute );
		} );
		// Open the dialog if the path is in the URL (for example, if the user reloads the page
		// while the dialog is open)
		routerInstance.checkRoute();
	};
	module.exports = attachButton;
}() );
