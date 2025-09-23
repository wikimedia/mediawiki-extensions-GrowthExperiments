( function () {
	const StartEditingDialog = require( './StartEditingDialog.js' ),
		Logger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepagePageviewToken' ),
		),
		rootStore = require( 'ext.growthExperiments.DataStore' );
	let isSuggestedEditsActivated = mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' ),
		// We pretend the module is activated on mobile for the purposes of the start editing
		// dialog interactions
		shouldSuggestedEditsAppearActivated = OO.ui.isMobile() ? true : isSuggestedEditsActivated,
		modalWindowManager;

	/**
	 * Launch the suggested edits initiation dialog.
	 *
	 * @param {string} module Which homepage module the dialog was launched from.
	 * @param {string} mode Rendering mode. See constants in IDashboardModule.php
	 * @param {string} trigger What caused the dialog to appear - 'impression' (when it was part of
	 *   the page from the start), 'welcome' (launched from the homepage welcome dialog),
	 *   'info-icon' (launched via the info icon in the suggested edits module header),
	 *   'impact' (when launched from the impact module).
	 * @return {jQuery.Promise<boolean>} Resolves when the dialog is closed, indicates whether
	 *   initiation was successful or cancelled.
	 */
	function launchCta( module, mode, trigger ) {
		const dialog = new StartEditingDialog( {
			module: module,
			mode: mode,
			trigger: trigger,
			useTopicSelector: !shouldSuggestedEditsAppearActivated,
			useTaskTypeSelector: !shouldSuggestedEditsAppearActivated,
			activateWhenDone: !isSuggestedEditsActivated,
		}, logger, rootStore );
		if ( !modalWindowManager ) {
			modalWindowManager = new OO.ui.WindowManager( {
				modal: true,
			} );
			// eslint-disable-next-line no-jquery/no-global-selector
			$( 'body' ).append( modalWindowManager.$element );
		}

		logger.log( module, mode, 'se-cta-click', { trigger: trigger } );

		modalWindowManager.addWindows( [ dialog ] );
		const lifecycle = modalWindowManager.openWindow( dialog );
		return lifecycle.closing.then( ( data ) => ( data && data.action === 'activate' ) );
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
			'#mw-ge-homepage-suggestededits-info',
		).each( ( _, button ) => {
			const trigger = 'info-icon',
				$button = $( button ),
				buttonType = $button.attr( 'id' ).slice( 'mw-ge-homepage-'.length ),
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

			buttonWidget.on( 'click', () => {
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
							new mw.Title( 'Special:Homepage/suggested-edits' ).toString(),
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
	 * @param {boolean} useTopicMatchMode If topic match mode feature is enabled in the UI
	 */
	function setupEmbeddedDialog( $container, useTopicMatchMode ) {
		// Only do this for the start-startediting module on desktop
		const $startEditingModule = $container.find(
			'.growthexperiments-homepage-module-start-startediting' +
			'.growthexperiments-homepage-module-desktop',
		);
		if ( $startEditingModule.length === 0 ) {
			return;
		}

		const mode = $startEditingModule.data( 'mode' );
		const dialog = new StartEditingDialog( {
			// For technical reasons we implement this dialog with the StartEditing module,
			// but conceptually it is the pre-initiation view of the SuggestedEdits module.
			module: 'suggested-edits',
			mode: mode,
			trigger: 'suggested-edits',
			useTopicSelector: true,
			useTaskTypeSelector: true,
			activateWhenDone: true,
			useTopicMatchMode: useTopicMatchMode,
		}, logger, rootStore );

		dialog.on( 'activation', () => {
			isSuggestedEditsActivated = true;
			shouldSuggestedEditsAppearActivated = true;
		} );
		const windowManager = new OO.ui.WindowManager( { modal: false } );
		$startEditingModule.append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );

		logger.log( 'suggested-edits', mode, 'se-cta-click', { trigger: 'impression' } );
	}

	/**
	 * Set up info button tooltip for suggested edits and if applicable, initialize embedded topics
	 * and task types filters dialog
	 *
	 * @param {jQuery} $container Container in which the module is initialized
	 * @param {boolean} useTopicMatchMode If topic match mode feature is enabled in the UI
	 */
	function initialize( $container, useTopicMatchMode ) {
		// Try setup for desktop mode and server-side-rendered mobile mode
		// See also the comment in ext.growthExperiments.Homepage.Mentorship/index.js.
		setupCtaButton( $container );
		if ( !isSuggestedEditsActivated ) {
			setupEmbeddedDialog( $container, useTopicMatchMode );
		}
	}

	/**
	 * Allow activation from guided tour, welcome drawer, impact module CTA, etc.
	 *
	 * @param {string} topic growthexperiments.startediting in all cases.
	 * @param {Object} data The data object passed by the module that called mw.track()
	 * @param {string} data.moduleName The name of the module
	 * @param {string} data.trigger The trigger to use in event logging
	 */
	mw.trackSubscribe( 'growthexperiments.startediting', ( topic, data ) => {
		// eslint-disable-next-line no-jquery/no-global-selector
		const mode = $( '.growthexperiments-homepage-module' ).data( 'mode' );
		launchCta( data.moduleName, mode, data.trigger );
	} );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( ( moduleName, $content ) => {
		if ( moduleName === 'suggested-edits' ) {
			setupCtaButton( $content );
		}
	} );

	module.exports = {
		initialize: initialize,
	};

}() );
