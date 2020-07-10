( function () {
	var Help = require( 'ext.growthExperiments.Help' ),
		Utils = require( '../utils/ext.growthExperiments.Utils.js' ),
		taskTypes = require( './TaskTypes.json' ),
		HelpPanelLogger = Help.HelpPanelLogger,
		HelpPanelProcessDialog = Help.HelpPanelProcessDialog,
		mobileFrontend = mw.mobileFrontend,
		Drawer = mobileFrontend ? mw.mobileFrontend.require( 'mobile.startup' ).Drawer : undefined,
		Anchor = mobileFrontend ? mw.mobileFrontend.require( 'mobile.startup' ).Anchor : undefined,
		configData = Help.configData,
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		suggestedEditsPeek = require( './ext.growthExperiments.SuggestedEditsPeek.js' ),
		guidanceEnabled = mw.config.get( 'wgGENewcomerTasksGuidanceEnabled' ),
		guidanceAvailable,
		taskTypeId,
		taskTypeLogData;

	if ( guidanceEnabled && suggestedEditSession.active &&
		!suggestedEditSession.postEditDialogNeedsToBeShown
	) {
		require( './../homepage/suggestededits/ext.growthExperiments.SuggestedEdits.Guidance.js' );
	}
	taskTypeId = suggestedEditSession.taskType;
	taskTypeLogData = taskTypeId ? { taskType: taskTypeId } : null;
	guidanceAvailable = guidanceEnabled && taskTypeId && taskTypes[ taskTypeId ];

	// This shouldn't happen, but just to be sure
	if ( !mw.config.get( 'wgGEHelpPanelEnabled' ) ) {
		return;
	}

	$( function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		var $buttonToInfuse = $( '#mw-ge-help-panel-cta-button' ),
			$buttonWrapper = $buttonToInfuse.parent(),
			$mfOverlay,
			$veUiOverlay,
			// eslint-disable-next-line no-jquery/no-global-selector
			$body = $( 'body' ),
			windowManager = new OO.ui.WindowManager( { modal: OO.ui.isMobile() } ),
			$overlay = $( '<div>' ).addClass( 'mw-ge-help-panel-widget-overlay' ),
			logger = new HelpPanelLogger( configData.GEHelpPanelLoggingEnabled, {
				previousEditorInterface: suggestedEditSession.editorInterface,
				// If the user is following a link in a suggested edit task card (which has a click ID,
				// which causes wgGEHomepagePageviewToken to be set on the server side), inherit
				// the session ID of the source page. Otherwise, preserve the suggested edit session's
				// ID, if we are in one.
				sessionId: mw.config.get( 'wgGEHomepagePageviewToken' ) ||
					suggestedEditSession.active && suggestedEditSession.clickId,
				isSuggestedTask: suggestedEditSession.active
			} ),
			size = OO.ui.isMobile() ? 'full' : 'small',
			/**
			 * @type {OO.ui.Window}
			 */
			helpPanelProcessDialog = new HelpPanelProcessDialog( {
				// Make help panel wider for larger screens.
				size: Math.max( document.documentElement.clientWidth, window.innerWidth || 0 ) > 1366 ? 'medium' : size,
				logger: logger,
				questionPosterAllowIncludingTitle: true,
				guidanceEnabled: guidanceEnabled,
				taskTypeId: taskTypeId,
				suggestedEditSession: suggestedEditSession
			} ),
			helpCtaButton,
			lifecycle;

		/**
		 * Invoked from mobileFrontend.editorOpened, ve.activationComplete
		 * and wikipage.editform hooks.
		 *
		 * The CTA needs to be (re-)attached to the overlay when VisualEditor or
		 * the MobileFrontend editor is opened.
		 *
		 * @param {Object|string} editor Which editor is being opened
		 */
		function attachHelpButton( editor ) {
			var metadataOverride = {};
			helpPanelProcessDialog.updateEditMode();
			// wikipage.editform gives us an object here, not a string.
			if ( typeof editor === 'object' && editor !== null ) {
				editor = 'wikitext';
			}
			if ( Utils.isValidEditor( editor ) ) {
				/* eslint-disable-next-line camelcase */
				metadataOverride.editor_interface = editor;
			}

			// Don't reattach the button wrapper if it's already attached to the overlay, otherwise
			// the animation happens twice
			if ( $buttonWrapper.parent()[ 0 ] !== $overlay[ 0 ] ) {
				$overlay.append( $buttonWrapper );
			}
			helpPanelProcessDialog.logger.log( 'impression', taskTypeLogData, metadataOverride );
		}

		/**
		 * Invoked from mobileFrontend.editorClosed and ve.deactivationComplete hooks.
		 *
		 * mobileFrontend.editorClosed is fired both during editor switching and
		 * when closing an editor to go back to reading mode.
		 *
		 * Hide the CTA when VisualEditor or the MobileFrontend editor is closed.
		 */
		function detachHelpButton() {
			// If there's a suggested edit session, we don't want to close the
			// panel if the user switches to Read mode.
			if ( !suggestedEditSession.active ) {
				windowManager.closeWindow( helpPanelProcessDialog );
			}
			helpPanelProcessDialog.updateEditMode();
			// If the help panel should show for the namespace, then don't detach the button
			// and also log an impression.
			if ( configData.GEHelpPanelReadingModeNamespaces.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) !== -1 ) {
				// When closing the wikitext editor, the url is only updated some time after
				// so there is a chance that we need to log an impression event but we'll
				// only know for sure a little later ;)
				setTimeout( function () {
					if ( helpPanelProcessDialog.logger.getEditor() === 'reading' ) {
						helpPanelProcessDialog.logger.log( 'impression', taskTypeLogData );
					}
				}, 250 );
				return;
			}
			if ( !guidanceAvailable ) {
				$buttonWrapper.detach();
			}
		}

		if ( $buttonToInfuse.length ) {
			helpCtaButton = OO.ui.ButtonWidget.static.infuse( $buttonToInfuse );
		} else {
			helpCtaButton = new OO.ui.ButtonWidget( {
				id: 'mw-ge-help-panel-cta-button',
				href: mw.util.getUrl( configData.GEHelpPanelHelpDeskTitle ),
				label: mw.msg( 'growthexperiments-help-panel-cta-button-text' ),
				invisibleLabel: true,
				flags: [ 'primary', 'progressive' ],
				// Only one of these two is visible at a time, with a transition between them
				// See ext.growthExperiments.HelpPanelCta.less
				icon: 'help',
				indicator: 'up'
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

		function openHelpPanel( panel ) {
			if ( OO.ui.isMobile() ) {
				// HACK: Detach the MobileFrontend overlay for both VE and source edit modes.
				// Per T212967, leaving them enabled results in a phantom text input that the
				// user can only see the cursor input for.
				// eslint-disable-next-line no-jquery/no-global-selector
				$mfOverlay = $( '.overlay' ).detach();
				// Detach the VE UI overlay, needed to prevent interference with scrolling in
				// our dialog.
				// eslint-disable-next-line no-jquery/no-global-selector
				$veUiOverlay = $( '.ve-ui-overlay' ).detach();
				// More hacks. WindowManager#toggleGlobalEvents adds the modal-active class,
				// which is styled with position:relative. This seems to interfere with search
				// results scroll on iOS.
				// Unlike the overlays which we detach above and re-append when the lifecycle
				// is closing, we do not need to re-add the class to the body since WindowManager
				// removes the class on closing.
				$body.removeClass( 'oo-ui-windowManager-modal-active' );
			}

			$buttonWrapper.addClass( 'mw-ge-help-panel-opened' );
			lifecycle = windowManager.openWindow( helpPanelProcessDialog, {
				panel: panel
			} );
			lifecycle.opening.then( function () {
				logger.log( 'open' );
				helpPanelProcessDialog.updateSuggestedEditSession( {
					helpPanelShouldOpen: true
				} );
				helpPanelProcessDialog.updateEditMode();
			} );
			lifecycle.closing.done( function () {
				if ( OO.ui.isMobile() ) {
					$body.append( $mfOverlay );
					$body.append( $veUiOverlay );
				}
				if ( guidanceAvailable ) {
					attachHelpButton( helpPanelProcessDialog.logger.getEditor() );
				}
				$buttonWrapper.removeClass( 'mw-ge-help-panel-opened' );
			} );
			return lifecycle;
		}

		/**
		 * Set the editor to VE if the user hasn't selected prefer wikitext in
		 * their editor preferences.
		 */
		function defaultToVisualEditorIfPossible() {
			if ( mw.user.options.get( 'visualeditor-tabs' ) !== 'prefer-wt' ) {
				mw.storage.set( 'preferredEditor', 'VisualEditor' );
			}
		}

		function maybeAddMobilePeek( taskTypeData ) {
			var mobilePeek,
				// Drawer.onBeforeHide fires whether the drawer was dismissed or tapped on
				// (and replaced with the full help panel). Use this flag to differentiate.
				tapped = false;

			// If we've already shown the mobile peek once, don't show it again
			// but do attach the help button
			if ( suggestedEditSession.mobilePeekShown ) {
				attachHelpButton( helpPanelProcessDialog.logger.getEditor() );
				return;
			}

			mobilePeek = new Drawer( {
				className: 'suggested-edits-mobile-peek',
				showCollapseIcon: false,
				children: [
					suggestedEditsPeek.getSuggestedEditsPeek(
						'suggested-edits-mobile-peek-content',
						taskTypeData.messages,
						taskTypeData.difficulty
					),
					$( '<div>' ).addClass( 'suggested-edits-mobile-peek-footer' )
						.append(
							new Anchor( {
								href: '#',
								additionalClassNames: 'suggested-edits-mobile-peek-more-about-this-edit',
								progressive: true,
								label: mw.msg(
									'growthexperiments-homepage-suggestededits-mobile-peek-more-about-this-edit'
								)
							} ).$el
						)
				],
				onBeforeHide: function ( drawer ) {
					if ( !tapped ) {
						logger.log( 'peek-dismiss' );
						// We still want to show the help button if the peek
						// was dismissed.
						attachHelpButton( helpPanelProcessDialog.logger.getEditor() );
					}
					setTimeout( function () {
						helpCtaButton.toggle( true );
						drawer.$el.remove();
					}, 250 );
				}
			} );
			mobilePeek.$el.find( '.suggested-edits-mobile-peek' ).on( 'click', function () {
				tapped = true;
				logger.log( 'peek-tap' );
				mobilePeek.hide();
				openHelpPanel( suggestedEditSession.helpPanelCurrentPanel || 'suggested-edits' );
			} );
			document.body.appendChild( mobilePeek.$el[ 0 ] );
			helpCtaButton.toggle( false );
			logger.log( 'peek-impression', taskTypeLogData );
			mobilePeek.show();
			suggestedEditSession.mobilePeekShown = true;
			suggestedEditSession.save();
		}

		if ( guidanceAvailable ) {
			if ( OO.ui.isMobile() ) {
				defaultToVisualEditorIfPossible();
				maybeAddMobilePeek( taskTypes[ taskTypeId ] );
			} else if ( suggestedEditSession.helpPanelShouldOpen ) {
				// Open the help panel to the suggested-edits panel, animating it in from the bottom
				// Perform this special animation only once, the first time the help panel opens
				$overlay.addClass( 'mw-ge-help-panel-popup-guidance' );
				openHelpPanel( suggestedEditSession.helpPanelCurrentPanel || 'suggested-edits' ).closing
					.done( function () {
						$overlay.removeClass( 'mw-ge-help-panel-popup-guidance' );
					} );
			} else {
				// If guidance is available we want to attach the help button
				// so the user can get back to it; this can happen if for example
				// the user reloads the page they're on (in Read mode) .
				attachHelpButton( helpPanelProcessDialog.logger.getEditor() );
			}
		}

		helpCtaButton.on( 'click', function () {
			if ( lifecycle && !lifecycle.isClosed() ) {
				helpPanelProcessDialog.executeAction( 'close' );
			} else {
				openHelpPanel(
					guidanceAvailable ?
						( suggestedEditSession.helpPanelCurrentPanel || 'suggested-edits' ) :
						'home'
				);
			}
		} );

		// Attach or detach the help panel CTA in response to hooks from MobileFrontend,
		// and set the logger's editor interface.
		if ( OO.ui.isMobile() ) {
			mw.hook( 'mobileFrontend.editorOpened' ).add(
				function ( editor ) {
					helpPanelProcessDialog.logger.setEditor( editor );
					attachHelpButton( editor );
				}
			);
			mw.hook( 'mobileFrontend.editorClosed' ).add(
				function ( editor ) {
					helpPanelProcessDialog.logger.setEditor( editor );
					detachHelpButton();
				}
			);
		} else {
			// VisualEditor activation hooks are ignored in mobile context because MobileFrontend
			// hooks are sufficient for attaching/detaching the help CTA.
			mw.hook( 've.activationComplete' ).add( attachHelpButton );
			mw.hook( 've.deactivationComplete' ).add( detachHelpButton );

			// Older wikitext editor
			mw.hook( 'wikipage.editform' ).add( attachHelpButton );
		}

		// If viewing an article, log the impression. Editing impressions are
		// logged via attachHelpButton(), but we don't need to utilize that
		// function on view.
		if ( !guidanceAvailable && logger.getContext() === 'reading' ) {
			helpPanelProcessDialog.logger.log( 'impression', taskTypeLogData );
		}
	} );

}() );
