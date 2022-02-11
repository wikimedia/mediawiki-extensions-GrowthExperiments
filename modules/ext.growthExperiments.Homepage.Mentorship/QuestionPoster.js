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
				router.addRoute( route, function () {
					var lifecycle = windowManager.openWindow( dialog, { panel: 'ask-help' } );
					logger.log( 'ask-help' );
					lifecycle.closing.done( function () {
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
				$( window ).on( 'hashchange', function () {
					if ( router.getPath() !== route ) {
						windowManager.closeWindow( dialog );
					}
				} );
			},
			questionRoute = '/homepage/' + config.dialog.name + '/question',
			suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
			QuestionPosterDialog, Help, loggerInstance, windowManagerInstance, ctaButton,
			dialogInstance, routerInstance;

		// no-op if the CTA button isn't found. This happens if the RL module is loaded
		// before the corresponding HTML is set in the DOM, as currently occurs with
		// the mobile homepage modules.
		if ( !$container.find( config.buttonSelector ).length ) {
			return;
		}

		routerInstance = require( 'mediawiki.router' );
		Help = require( 'ext.growthExperiments.Help' );
		QuestionPosterDialog = Help.HelpPanelProcessDialog;
		loggerInstance = new Help.HelpPanelLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			{
				context: config.context,
				sessionId: mw.config.get( 'wgGEHomepagePageviewToken' )
			}
		);
		windowManagerInstance = new OO.ui.WindowManager( { modal: true } );
		suggestedEditSession.helpPanelShouldBeLocked = true;
		dialogInstance = new QuestionPosterDialog( $.extend( {
			size: 'medium',
			logger: loggerInstance,
			layoutType: 'dialog',
			questionPosterAllowIncludingTitle: false,
			suggestedEditSession: suggestedEditSession,
			showCogMenu: false,
			askSource: 'mentor-homepage',
			isQuestionPoster: true
		}, config.dialog ) );
		ctaButton = OO.ui.ButtonWidget.static.infuse( $container.find( config.buttonSelector ) );

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
		ctaButton.on( 'click', function () {
			routerInstance.navigate( '#' + questionRoute );
		} );
		// Open the dialog if the path is in the URL (for example, if the user reloads the page
		// while the dialog is open)
		routerInstance.checkRoute();
	};
	module.exports = attachButton;
}() );
