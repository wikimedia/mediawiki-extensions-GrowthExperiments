( function ( M ) {
	'use strict';
	var attachButton = function ( config ) {
		var appendWindowManagerToBody = function ( windowManager, dialog ) {
				// eslint-disable-next-line no-jquery/no-global-selector
				$( 'body' ).append( windowManager.$element );
				windowManager.addWindows( [ dialog ] );
			},
			/**
			 * Register the route for the question dialog.
			 * @param {OO.Router} router
			 * @param {string} route
			 * @param {OO.ui.WindowManager} windowManager
			 * @param {QuestionPosterDialog} dialog
			 * @param {Help.HelpPanelLogger} logger
			 */
			registerDialogRoute = function ( router, route, windowManager, dialog, logger ) {
				router.addRoute( route, function () {
					var lifecycle = windowManager.openWindow( dialog );
					dialog.executeAction( 'questionreview' );
					lifecycle.closing.done( function () {
						logger.log( 'close' );
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
			QuestionPosterDialog, Help, loggerInstance, windowManagerInstance, ctaButton,
			dialogInstance, routerInstance;

		// no-op if the CTA button isn't found. This happens if the RL module is loaded
		// before the corresponding HTML is set in the DOM, as currently occurs with
		// the mobile homepage modules.
		if ( !$( config.buttonSelector ).length ) {
			return;
		}

		routerInstance = require( 'mediawiki.router' );
		QuestionPosterDialog = require( './ext.growthExperiments.QuestionPosterDialog.js' );
		Help = require( 'ext.growthExperiments.Help' );
		loggerInstance = new Help.HelpPanelLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			{
				editorInterface: config.editorInterface,
				sessionId: mw.config.get( 'wgGEHomepagePageviewToken' )
			}
		);
		windowManagerInstance = new OO.ui.WindowManager( { modal: true } );
		dialogInstance = new QuestionPosterDialog( $.extend( {
			size: 'medium',
			logger: loggerInstance
		}, config.dialog ) );
		ctaButton = OO.ui.ButtonWidget.static.infuse( $( config.buttonSelector ) );

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
	};
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function () {
		var mobile = M.require( 'mobile.startup' ),
			time = mobile.time;
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.question-posted-on' ).each( function ( index, value ) {
			var $postedOn = $( value ),
				timestamp = $postedOn.data( 'timestamp' ),
				keys = {
					// These keys come from Language->formatDuration() which we use
					// on the server-side.
					seconds: 'duration-seconds',
					minutes: 'duration-minutes',
					hours: 'duration-hours',
					days: 'duration-days',
					weeks: 'duration-weeks',
					years: 'duration-years'
				},
				delta;

			if ( !timestamp ) {
				return;
			}
			delta = time.getTimeAgoDelta( parseInt( timestamp, 10 ) );
			$postedOn.text(
				mw.message(
					'growthexperiments-homepage-recent-questions-posted-on',
					mw.message.apply(
						this,
						[ keys[ delta.unit ], mw.language.convertNumber( delta.value ) ]
					).text()
				).parse()
			);
		} );
	} );
	module.exports = attachButton;
}( mw.mobileFrontend ) );
