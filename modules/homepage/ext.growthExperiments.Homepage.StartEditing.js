( function () {
	var StartEditingDialog = require( './ext.growthExperiments.Homepage.StartEditingDialog.js' ),
		Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		defaultTaskTypes = require( './suggestededits/DefaultTaskTypes.json' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		GrowthTasksApi = require( './suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
		api = new GrowthTasksApi( {
			defaultTaskTypes: defaultTaskTypes,
			isMobile: OO.ui.isMobile(),
			context: 'startEditingDialog'
		} );

	/**
	 * Launch the suggested edits initiation dialog.
	 *
	 * @param {string} type 'startediting-cta', 'startediting-mobilesummary-cta' or 'suggestededits-info'
	 * @param {string} mode Rendering mode. See constants in HomepageModule.php
	 * @return {jQuery.Promise<boolean>} Resolves when the dialog is closed, indicates whether
	 *   initiation was successful or cancelled.
	 */
	function launchCta( type, mode ) {
		var lifecycle, dialog, windowManager;

		dialog = new StartEditingDialog( {
			mode: mode,
			useTopicSelector: type === 'startediting-cta' || type === 'startediting-mobilesummary-cta',
			useTaskTypeSelector: type === 'startediting-mobilesummary-cta',
			activateWhenDone: type === 'startediting-cta' || type === 'startediting-mobilesummary-cta'
		}, logger, api );
		windowManager = new OO.ui.WindowManager( {
			modal: true
		} );

		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		lifecycle = windowManager.openWindow( dialog );
		return lifecycle.closing.then( function ( data ) {
			return ( data && data.action === 'activate' );
		} );
	}

	function setupCta( $container ) {
		$container.find(
			'#mw-ge-homepage-startediting-cta, ' +
			'#mw-ge-homepage-suggestededits-info'
		).each( function ( _, button ) {
			var $button = $( button ),
				buttonType = $button.attr( 'id' ).substr( 'mw-ge-homepage-'.length ),
				mode = $button.closest( '.growthexperiments-homepage-module' ).data( 'mode' ),
				buttonWidget = OO.ui.ButtonWidget.static.infuse( $button );

			// Don't attach the click handler to the same button twice
			if ( $button.data( 'mw-ge-homepage-startediting-cta-setup' ) ) {
				return;
			}
			$button.data( 'mw-ge-homepage-startediting-cta-setup', true );

			buttonWidget.on( 'click', function () {
				if (
					buttonType === 'startediting-cta' &&
					mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' )
				) {
					// already set up, just open suggested edits
					if ( mode === 'mobile-overlay' ) {
						// we don't want users to return to the start overlay when they close
						// suggested edits
						window.history.replaceState( null, null, '#/homepage/suggested-edits' );
						window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
					} else if ( mode === 'mobile-details' ) {
						window.location.href = mw.util.getUrl(
							new mw.Title( 'Special:Homepage/suggested-edits' ).toString()
						);
					}
					return;
				}

				if ( buttonType === 'startediting-cta' ) {
					logger.log( 'start-startediting', mode, 'se-cta-click' );
				} else if ( buttonType === 'suggestededits-info' ) {
					logger.log( 'suggested-edits', mode, 'se-info-click' );
				}
				launchCta( buttonType, mode ).done( function ( activated ) {
					if ( activated ) {
						// No-op; logging and everything else is done within the dialog,
						// as it is kept open during setup of the suggested edits module
						// to make the UI change less disruptive.
					} else if ( buttonType === 'startediting-cta' ) {
						logger.log( 'start-startediting', mode, 'se-cancel-activation' );
					}
				} );
			} );
		} );
	}

	/**
	 * Create a StartEditing dialog, and embed it inside the start-startediting module.
	 * Only used for variant D on desktop; this function is a no-op otherwise.
	 *
	 * @param {jQuery} $container DOM element that contains homepage modules
	 */
	function setupEmbeddedDialog( $container ) {
		var mode, dialog, windowManager,
			// Only do this for the start-startediting module in variant D on desktop
			$startEditingModule = $container.find(
				'.growthexperiments-homepage-module-start-startediting' +
				'.growthexperiments-homepage-module-desktop' +
				'.growthexperiments-homepage-module-user-variant-D'
			);
		if ( $startEditingModule.length === 0 ) {
			return;
		}

		mode = $startEditingModule.data( 'mode' );
		dialog = new StartEditingDialog( {
			mode: mode,
			useTopicSelector: true,
			useTaskTypeSelector: true,
			activateWhenDone: true
		}, logger, api );
		windowManager = new OO.ui.WindowManager( { modal: false } );
		$startEditingModule.append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js
	// eslint-disable-next-line no-jquery/no-global-selector
	setupCta( $( '.growthexperiments-homepage-container' ) );
	// eslint-disable-next-line no-jquery/no-global-selector
	setupEmbeddedDialog( $( '.growthexperiments-homepage-container' ) );

	// Allow activation from a guided tour
	mw.trackSubscribe( 'growthexperiments.startediting', function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		var mode = $( '.growthexperiments-homepage-module' ).data( 'mode' );
		launchCta( 'suggestededits', mode );
	} );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'start' || moduleName === 'suggested-edits' ) {
			setupCta( $content );
		}
	} );

	mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.start-startediting' ).add( function ( $content ) {
		$content.on( 'click', function () {
			logger.log( 'start-startediting', $content.data( 'mode' ), 'se-mobilesummary-cta-click' );
			launchCta( 'startediting-mobilesummary-cta', 'mobile-summary' );
		} );
	} );

}() );
