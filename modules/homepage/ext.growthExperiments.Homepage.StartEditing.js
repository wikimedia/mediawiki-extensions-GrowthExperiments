( function () {
	var StartEditingDialog = require( './ext.growthExperiments.Homepage.StartEditingDialog.js' ),
		Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		TaskTypesAbFilter = require( './suggestededits/TaskTypesAbFilter.js' ),
		defaultTaskTypes = TaskTypesAbFilter.filterDefaultTaskTypes(
			require( './suggestededits/DefaultTaskTypes.json' ) ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		GrowthTasksApi = require( './suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
		isSuggestedEditsActivated = mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' ),
		// We pretend the module is activated on mobile for the purposes of the start editing
		// dialog interactions
		shouldSuggestedEditsAppearActivated = OO.ui.isMobile() ? true : isSuggestedEditsActivated,
		api = new GrowthTasksApi( {
			defaultTaskTypes: defaultTaskTypes,
			isMobile: OO.ui.isMobile(),
			context: 'startEditingDialog'
		} );

	/**
	 * Launch the suggested edits initiation dialog.
	 *
	 * @param {string} module Which homepage module the dialog was launched from.
	 * @param {string} mode Rendering mode. See constants in HomepageModule.php
	 * @param {string} trigger What caused the dialog to appear - 'impression' (when it was part of
	 *   the page from the start), 'welcome' (launched from the homepage welcome dialog),
	 *   'info-icon' (launched via the info icon in the suggested edits module header),
	 *   'impact' (when launched from the impact module).
	 * @return {jQuery.Promise<boolean>} Resolves when the dialog is closed, indicates whether
	 *   initiation was successful or cancelled.
	 */
	function launchCta( module, mode, trigger ) {
		var lifecycle, dialog, windowManager;

		dialog = new StartEditingDialog( {
			module: module,
			mode: mode,
			trigger: trigger,
			useTopicSelector: !shouldSuggestedEditsAppearActivated,
			useTaskTypeSelector: !shouldSuggestedEditsAppearActivated,
			activateWhenDone: !isSuggestedEditsActivated
		}, logger, api );
		windowManager = new OO.ui.WindowManager( {
			modal: true
		} );

		logger.log( module, mode, 'se-cta-click', { trigger: trigger } );

		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		lifecycle = windowManager.openWindow( dialog );
		return lifecycle.closing.then( function ( data ) {
			return ( data && data.action === 'activate' );
		} );
	}

	/**
	 * Add event handler to a button for launching the suggested edit dialog.
	 *
	 * @param {jQuery} $container The element which contains the button. The button should have
	 *   the #mw-ge-homepage-startediting-cta or #mw-ge-homepage-suggestededits-info ID, and
	 *   be inside a homepage module.
	 */
	function setupCtaButton( $container ) {
		$container.find(
			'#mw-ge-homepage-startediting-cta, ' +
			'#mw-ge-homepage-suggestededits-info'
		).each( function ( _, button ) {
			var trigger = 'info-icon',
				$button = $( button ),
				buttonType = $button.attr( 'id' ).substr( 'mw-ge-homepage-'.length ),
				// From the mobile overlay header one cannot traverse the DOM tree upwards to find a
				// homepage module, so for mobile overlay only we embed the module-name and
				// mode. In all other cases we infer it from the DOM context.
				module = $button.data( 'module-name' ) || $button.closest( '.growthexperiments-homepage-module' ).data( 'module-name' ),
				mode = $button.data( 'mode' ) || $button.closest( '.growthexperiments-homepage-module' ).data( 'mode' ),
				buttonWidget = OO.ui.ButtonWidget.static.infuse( $button );

			// Don't attach the click handler to the same button twice
			if ( $button.data( 'mw-ge-homepage-startediting-cta-setup' ) ) {
				return;
			}
			$button.data( 'mw-ge-homepage-startediting-cta-setup', true );

			buttonWidget.on( 'click', function () {
				if (
					buttonType === 'startediting-cta' &&
					isSuggestedEditsActivated
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

				launchCta( module, mode, trigger );
			} );
		} );
	}

	/**
	 * Create a StartEditing dialog, and embed it inside the start-startediting module.
	 * Only used for unactivated suggested edits on desktop; this function is a no-op otherwise.
	 *
	 * @param {jQuery} $container DOM element that contains homepage modules
	 */
	function setupEmbeddedDialog( $container ) {
		var mode, dialog, windowManager,
			// Only do this for the start-startediting module on desktop
			$startEditingModule = $container.find(
				'.growthexperiments-homepage-module-start-startediting' +
				'.growthexperiments-homepage-module-desktop'
			);
		if ( $startEditingModule.length === 0 ) {
			return;
		}

		mode = $startEditingModule.data( 'mode' );
		dialog = new StartEditingDialog( {
			// For technical reasons we implement this dialog with the StartEditing module,
			// but conceptually it is the pre-initiation view of the SuggestedEdits module.
			module: 'suggested-edits',
			mode: mode,
			trigger: 'suggested-edits',
			useTopicSelector: true,
			useTaskTypeSelector: true,
			activateWhenDone: true
		}, logger, api );

		dialog.on( 'activation', function ( $activatedModuleContainer ) {
			isSuggestedEditsActivated = true;
			shouldSuggestedEditsAppearActivated = true;
			// Setup the info icon to launch dialogs.
			setupCtaButton( $activatedModuleContainer );
		} );
		windowManager = new OO.ui.WindowManager( { modal: false } );
		$startEditingModule.append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );

		logger.log( 'suggested-edits', mode, 'se-cta-click', { trigger: 'impression' } );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js
	// eslint-disable-next-line no-jquery/no-global-selector
	setupCtaButton( $( '.growthexperiments-homepage-container' ) );
	// eslint-disable-next-line no-jquery/no-global-selector
	setupEmbeddedDialog( $( '.growthexperiments-homepage-container' ) );

	/**
	 * Allow activation from guided tour, welcome drawer, impact module CTA, etc.
	 *
	 * @param {string} topic growthexperiments.startediting in all cases.
	 * @param {Object} data The data object passed by the module that called mw.track()
	 * @param {string} data.moduleName The name of the module
	 * @param {string} data.trigger The trigger to use in event logging
	 */
	mw.trackSubscribe( 'growthexperiments.startediting', function ( topic, data ) {
		// eslint-disable-next-line no-jquery/no-global-selector
		var mode = $( '.growthexperiments-homepage-module' ).data( 'mode' );
		launchCta( data.moduleName, mode, data.trigger );
	} );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'start' || moduleName === 'suggested-edits' ) {
			setupCtaButton( $content );
		}
	} );

	mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.start-startediting' ).add( function ( $content ) {
		$content.on( 'click', function () {
			launchCta( 'start-startediting', $content.data( 'mode' ), 'suggested-edits' );
		} );
	} );

}() );
