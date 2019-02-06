( function () {
	// This shouldn't happen, but just to be sure
	if ( !mw.config.get( 'wgGEHelpPanelEnabled' ) ) {
		return;
	}

	$( function () {
		var $buttonToInfuse = $( '#mw-ge-help-panel-cta-button' ),
			$buttonWrapper = $buttonToInfuse.parent(),
			$mfOverlay,
			$veUiOverlay,
			$body = $( 'body' ),
			windowManager = new OO.ui.WindowManager( { modal: OO.ui.isMobile() } ),
			$overlay = $( '<div>' ).addClass( 'mw-ge-help-panel-widget-overlay' ),
			loggingEnabled = mw.config.get( 'wgGEHelpPanelLoggingEnabled' ),
			logger = new mw.libs.ge.HelpPanelLogger( loggingEnabled ),
			size = OO.ui.isMobile() ? 'full' : 'small',
			/**
			 * @type {OO.ui.Window}
			 */
			helpPanelProcessDialog = new mw.libs.ge.HelpPanelProcessDialog( {
				// Make help panel wider for larger screens.
				size: Math.max( document.documentElement.clientWidth, window.innerWidth || 0 ) > 1366 ? 'medium' : size,
				logger: logger
			} ),
			helpCtaButton,
			lifecycle,
			veTarget,
			mobileFrontendArticleTarget;

		/**
		 * Invoked from mobileFrontend.editorOpened, ve.activationComplete
		 * and wikipage.editform hooks.
		 *
		 * The CTA needs to be (re-)attached to the overlay when VisualEditor or
		 * the MobileFrontend editor is opened.
		 *
		 * @param {string} editor Which editor is being opened
		 */
		function attachHelpButton( editor ) {
			var metadataOverride = {};
			if ( logger.isValidEditor( editor ) ) {
				/* eslint-disable-next-line camelcase */
				metadataOverride.editor_interface = editor;
			}
			// Don't reattach the button wrapper if it's already attached to the overlay, otherwise
			// the animation happens twice
			if ( $buttonWrapper.parent()[ 0 ] !== $overlay[ 0 ] ) {
				$overlay.append( $buttonWrapper );
				logger.logOnce( 'impression', null, metadataOverride );
			}
		}

		/**
		 * Invoked from mobileFrontend.editorClosed and ve.deactivationComplete hooks.
		 *
		 * Hide the CTA when VisualEditor or the MobileFrontend editor is closed.
		 */
		function detachHelpButton() {
			$buttonWrapper.detach();
		}

		if ( $buttonToInfuse.length ) {
			helpCtaButton = OO.ui.ButtonWidget.static.infuse( $buttonToInfuse );
		} else {
			helpCtaButton = new OO.ui.ButtonWidget( {
				id: 'mw-ge-help-panel-cta-button',
				href: mw.util.getUrl( mw.config.get( 'wgGEHelpPanelHelpDeskTitle' ) ),
				label: OO.ui.isMobile() ? '' : mw.msg( 'growthexperiments-help-panel-cta-button-text' ),
				icon: 'askQuestion',
				flags: [ 'primary', 'progressive' ]
			} );
			$buttonWrapper = $( '<div>' )
				.addClass( 'mw-ge-help-panel-cta' )
				.append( helpCtaButton.$element );
			if ( OO.ui.isMobile() ) {
				$buttonWrapper.addClass( 'mw-ge-help-panel-cta-mobile' );
			}
		}
		$buttonWrapper.addClass( 'mw-ge-help-panel-ready' );

		$overlay.append( windowManager.$element );
		if ( !OO.ui.isMobile() ) {
			$overlay.addClass( 'mw-ge-help-panel-popup' );
		}
		$body.append( $overlay );
		windowManager.addWindows( [ helpPanelProcessDialog ] );

		helpCtaButton.on( 'click', function () {
			veTarget = OO.getProp( window, 've', 'init', 'target' );
			mobileFrontendArticleTarget = OO.getProp( window, 've', 'init', 'mw', 'MobileFrontendArticleTarget' );
			if ( veTarget && mobileFrontendArticleTarget &&
				veTarget instanceof mobileFrontendArticleTarget &&
				veTarget.onWindowScrollDebounced ) {
				// HACK: Remove the scroll event handler from the VE target window on mobile.
				// This is necessary because on iOS Safari, tapping into the text area in the
				// help panel process dialog triggers a scroll event on the VE surface, and then
				// the code in ve.init.mw.MobileFrontendArticleTarget#onWindowScroll
				// scrolls our process dialog textarea out of view. See T212967.
				$( veTarget.getElementWindow() ).off( 'scroll', veTarget.onWindowScrollDebounced );
			}

			if ( OO.ui.isMobile() ) {
				// HACK: Detach the MobileFrontend overlay for both VE and source edit modes.
				// Per T212967, leaving them enabled results in a phantom text input that the
				// user can only see the cursor input for.
				$mfOverlay = $( '.overlay' ).detach();
				// Detach the VE UI overlay, needed to prevent interference with scrolling in
				// our dialog.
				$veUiOverlay = $( '.ve-ui-overlay' ).detach();
			}
			lifecycle = windowManager.openWindow( helpPanelProcessDialog );
			// Reset to home panel if user closed the widget.
			helpPanelProcessDialog.executeAction( 'reset' );
			helpCtaButton.toggle( false );
			logger.log( 'open' );
			lifecycle.closing.done( function () {
				if ( veTarget && mobileFrontendArticleTarget &&
					veTarget instanceof mobileFrontendArticleTarget &&
					veTarget.onWindowScrollDebounced ) {
					// Re-attach the VE window scroll event handler when the help panel is closed.
					$( veTarget.getElementWindow() ).on( 'scroll', veTarget.onWindowScrollDebounced );
				}
				// Re-attach the MobileFrontend and VE overlays on mobile.
				if ( OO.ui.isMobile() ) {
					$body.append( $mfOverlay );
					$body.append( $veUiOverlay );
				}
				helpCtaButton.toggle( true );
				logger.log( 'close' );
			} );
		} );

		// Attach or detach the help panel CTA in response to hooks from MobileFrontend.
		if ( OO.ui.isMobile() ) {
			mw.hook( 'mobileFrontend.editorOpened' ).add( attachHelpButton );
			mw.hook( 'mobileFrontend.editorClosed' ).add( detachHelpButton );
		} else {
			// VisualEditor activation hooks are ignored in mobile context because MobileFrontend
			// hooks are sufficient for attaching/detaching the help CTA.
			mw.hook( 've.activationComplete' ).add( attachHelpButton );
			mw.hook( 've.deactivationComplete' ).add( detachHelpButton );

			// Older wikitext editor
			mw.hook( 'wikipage.editform' ).add( attachHelpButton );
		}
	} );

}() );
