( function () {
	const Help = require( 'ext.growthExperiments.Help' ),
		HelpPanelButton = require( '../ui-components/HelpPanelButton.js' ),
		Utils = require( '../utils/Utils.js' ),
		TASK_TYPES = require( 'ext.growthExperiments.DataStore' ).CONSTANTS.ALL_TASK_TYPES,
		HelpPanelLogger = Help.HelpPanelLogger,
		HelpPanelProcessDialog = Help.HelpPanelProcessDialog,
		mobileStatus = mw.loader.getState( 'mobile.startup' ),
		mobileFrontendEnabled = mobileStatus && mobileStatus !== 'registered',
		Drawer = mobileFrontendEnabled ? require( 'mobile.startup' ).Drawer : undefined,
		configData = Help.configData,
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		suggestedEditsPeek = require( '../ui-components/SuggestedEditsPeek.js' ),
		askHelpEnabled = mw.config.get( 'wgGEAskQuestionEnabled' );
	if ( suggestedEditSession.active &&
		!suggestedEditSession.postEditDialogNeedsToBeShown
	) {
		require( './SuggestedEditsGuidance.js' );
	}
	const taskTypeId = suggestedEditSession.taskType;
	const taskTypeLogData = taskTypeId ? { taskType: taskTypeId } : null;
	const guidanceAvailable = taskTypeId && TASK_TYPES[ taskTypeId ];

	$( () => {
		// eslint-disable-next-line no-jquery/no-global-selector
		const $buttonToInfuse = $( '#mw-ge-help-panel-cta-button' ),
			// eslint-disable-next-line no-jquery/no-global-selector
			$body = $( 'body' ),
			windowManager = new OO.ui.WindowManager( { modal: OO.ui.isMobile() } ),
			$overlay = $( '<div>' ).addClass( 'mw-ge-help-panel-widget-overlay' ),
			// If standardized menu area is available - use that.
			helpButtonLocationDock = mw.util.addPortletLink( 'p-dock-bottom', '#', '' ),
			helpButtonLocation = helpButtonLocationDock ?
				$( helpButtonLocationDock ).html( '' ) : $overlay,
			logger = new HelpPanelLogger( {
				previousEditorInterface: suggestedEditSession.editorInterface,
				// If the user is following a link in a suggested edit task card (which has a
				// click ID, which causes wgGEHomepagePageviewToken to be set on the server side),
				// inherit the session ID of the source page. Otherwise, preserve the suggested
				// edit session's ID, if we are in one.
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
				askHelpEnabled: askHelpEnabled,
				taskTypeId: taskTypeId,
				suggestedEditSession: suggestedEditSession
			} );
		let $buttonWrapper = $buttonToInfuse.parent(),
			isCtaHidden = true,
			lifecycle;

		// Export the help panel to mw.libs.ge, so that other components can close it if needed.
		window.mw.libs.ge = window.mw.libs.ge || {};
		window.mw.libs.ge.HelpPanel = helpPanelProcessDialog;

		/**
		 * Show or hide the help button after it's been attached
		 *
		 * @param {boolean} isHelpButtonVisible Whether the help button should be shown
		 */
		function toggleHelpButtonVisibility( isHelpButtonVisible ) {
			$buttonWrapper.toggleClass( 'mw-ge-help-panel-ready', isHelpButtonVisible );
		}

		/**
		 * Invoked from mobileFrontend.editorOpened, ve.activationComplete
		 * and wikipage.editform hooks.
		 *
		 * The CTA needs to be (re-)attached to the overlay when VisualEditor or
		 * the MobileFrontend editor is opened.
		 *
		 * @param {Object|string} editor Which editor is being opened
		 * @param {boolean} [isFirstTime] Whether the help panel button is shown for
		 * the first time (vs re-attached when the editor loads)
		 */
		function attachHelpButton( editor, isFirstTime ) {
			const metadataOverride = {};
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
				$buttonWrapper.appendTo( helpButtonLocation );
				isCtaHidden = false;
			} else if ( isCtaHidden ) {
				toggleHelpButtonVisibility( true );
				isCtaHidden = false;
			}
			helpPanelProcessDialog.logger.log( 'impression', taskTypeLogData, metadataOverride );
			if ( isFirstTime ) {
				suggestedEditSession.trackGuidanceShown();
			}
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
			if ( configData.GEHelpPanelReadingModeNamespaces.includes( mw.config.get( 'wgNamespaceNumber' ) ) ) {
				// When closing the wikitext editor, the url is only updated some time after
				// so there is a chance that we need to log an impression event but we'll
				// only know for sure a little later ;)
				setTimeout( () => {
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

		/**
		 * Hide the CTA when VE's context item is opened and show it when the context item is closed
		 */
		function setupHelpButtonToggle() {
			const onContextResize = function () {
				const isContextItemVisible = window.ve.init.target.surface.context.isVisible();
				$buttonWrapper.toggleClass( 'animate-out', isContextItemVisible );
			};
			const onContextResizeDebounced = OO.ui.debounce( onContextResize, 200 );
			mw.hook( 've.activationComplete' ).add( () => {
				window.ve.init.target.surface.context.on( 'resize', onContextResizeDebounced );
			} );
		}

		$overlay.append( windowManager.$element );
		if ( !OO.ui.isMobile() ) {
			$overlay.addClass( 'mw-ge-help-panel-popup' );
		}
		$body.append( $overlay );
		windowManager.addWindows( [ helpPanelProcessDialog ] );

		let helpCtaButton;
		if ( $buttonToInfuse.length ) {
			helpCtaButton = mw.libs.ge.HelpPanelButton.static.infuse( $buttonToInfuse );
			// The button is already on the page, but it won't be visible until we add the -ready
			// class to $buttonWrapper. While it's not yet visible, relocate it into the menu.
			$buttonWrapper.appendTo( helpButtonLocation );
		} else {
			helpCtaButton = new HelpPanelButton( {
				label: mw.msg( 'growthexperiments-help-panel-cta-button-text' ),
				href: mw.util.getUrl( configData.GEHelpPanelHelpDeskTitle )
			} );
			$buttonWrapper = $( '<div>' )
				.addClass( 'mw-ge-help-panel-cta' )
				.append( helpCtaButton.$element );
		}
		// Make the button visible (with slide up animation) if it's already on the page
		toggleHelpButtonVisibility( true );

		function openHelpPanel( panel ) {
			let $mfOverlay, $veUiOverlay;
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
			lifecycle.opening.then( () => {
				logger.log( 'open' );
				helpPanelProcessDialog.updateSuggestedEditSession( {
					helpPanelShouldOpen: true
				} );
				helpPanelProcessDialog.updateEditMode();
				helpCtaButton.setOpen( true );
			} );
			lifecycle.closing.then( () => {
				if ( OO.ui.isMobile() ) {
					$body.append( $mfOverlay, $veUiOverlay );
				}
				if ( guidanceAvailable ) {
					attachHelpButton( helpPanelProcessDialog.logger.getEditor() );
				}
				$buttonWrapper.removeClass( 'mw-ge-help-panel-opened' );
				helpPanelProcessDialog.setGuidanceAutoAdvance( false );
				helpCtaButton.setOpen( false );
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

		/**
		 * Show the mobile peek if it hasn't been shown during the session.
		 * If the mobile peek has already been shown, the help panel button is shown.
		 *
		 * @param {Object} taskTypeData
		 */
		function maybeAddMobilePeek( taskTypeData ) {
			let // Drawer.onBeforeHide fires whether the drawer was dismissed or tapped on
				// (and replaced with the full help panel). Use this flag to differentiate.
				tapped = false;

			// If we've already shown the mobile peek once, don't show it again
			// but do attach the help button
			if ( suggestedEditSession.mobilePeekShown ) {
				attachHelpButton( helpPanelProcessDialog.logger.getEditor(), true );
				return;
			}

			const mobilePeek = new Drawer( {
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
							$( '<a>' ).attr( {
								href: '#',
								class: 'suggested-edits-mobile-peek-more-about-this-edit'
							} ).text(
								mw.msg(
									'growthexperiments-homepage-suggestededits-mobile-peek-more-about-this-edit'
								)
							)
						)
				],
				onBeforeHide: function ( drawer ) {
					if ( !tapped ) {
						logger.log( 'peek-dismiss' );
						// We still want to show the help button if the peek
						// was dismissed.
						attachHelpButton( helpPanelProcessDialog.logger.getEditor() );
					}
					setTimeout( () => {
						helpCtaButton.toggle( true );
						drawer.$el.remove();
					}, 250 );
				}
			} );
			mobilePeek.$el.find( '.suggested-edits-mobile-peek' ).on( 'click', () => {
				tapped = true;
				logger.log( 'peek-tap' );
				mobilePeek.hide();
				openHelpPanel( suggestedEditSession.helpPanelCurrentPanel || 'suggested-edits' );
				// When the mobile peek is shown, the help panel is considered "opened", so the
				// auto-advance behavior is treated as if the help panel started out opened.
				helpPanelProcessDialog.setGuidanceAutoAdvance(
					helpPanelProcessDialog.shouldAutoAdvanceUponInit()
				);
			} );
			document.body.appendChild( mobilePeek.$el[ 0 ] );
			helpCtaButton.toggle( false );
			logger.log( 'peek-impression', taskTypeLogData );
			mobilePeek.show();
			suggestedEditSession.trackGuidanceShown();
			suggestedEditSession.mobilePeekShown = true;
			suggestedEditSession.save();
		}

		if ( guidanceAvailable ) {
			if ( OO.ui.isMobile() ) {
				defaultToVisualEditorIfPossible();
				maybeAddMobilePeek( TASK_TYPES[ taskTypeId ] );
			} else {
				// If guidance is available we want to attach the help button
				// so the user can get back to it; this can happen if for example
				// the user reloads the page they're on (in Read mode).
				attachHelpButton( helpPanelProcessDialog.logger.getEditor(), true );

				if ( suggestedEditSession.helpPanelShouldOpen ) {
					// Open the help panel to the suggested-edits panel, animating it in from
					// the bottom. Perform this special animation only once, the first time the
					// help panel opens.
					$overlay.addClass( 'mw-ge-help-panel-popup-guidance' );
					openHelpPanel( suggestedEditSession.helpPanelCurrentPanel || 'suggested-edits' ).closing
						.then( () => {
							$overlay.removeClass( 'mw-ge-help-panel-popup-guidance' );
						} );
				}
			}
		}

		helpCtaButton.on( 'click', () => {
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
			if ( suggestedEditSession.shouldOpenArticleInEditMode ) {
				/* Hide CTA if the article is opened in edit mode automatically
				 * since the CTA appears on top of the context item on mobile.
				 * Help panel can be invoked from help button in the context item.
				 */
				$buttonWrapper.addClass( 'oo-ui-element-hidden' );
				mw.hook( 'growthExperiments.contextItem.openHelpPanel' ).add(
					( helpPanelButton ) => {
						const prevScrollPosition = $( document ).scrollTop();
						openHelpPanel( 'suggested-edits' ).closing.then( () => {
							// When help panel closes, article is scrolled to 0.
							// Make sure annotation is visible.
							$( document ).scrollTop( prevScrollPosition );
							if ( helpPanelButton ) {
								helpPanelButton.setOpen( false );
							}
						} );
					}
				);
			}

			mw.hook( 'mobileFrontend.editorOpened' ).add(
				( editor ) => {
					helpPanelProcessDialog.logger.setEditor( editor );
					attachHelpButton( editor );

				}
			);
			mw.hook( 'mobileFrontend.editorClosed' ).add(
				( editor ) => {
					helpPanelProcessDialog.logger.setEditor( editor );
					detachHelpButton();
				}
			);

			setupHelpButtonToggle();

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

		// Allow the CTA to be hidden when there's a completing overlay (ex: post-edit dialog)
		mw.hook( 'helpPanel.hideCta' ).add( () => {
			isCtaHidden = true;
			toggleHelpButtonVisibility( false );
		} );
	} );

}() );
