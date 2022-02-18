( function () {
	'use strict';

	var HomepageModuleLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' ),
		TaskTypesAbFilter = require( '../ext.growthExperiments.Homepage.SuggestedEdits/TaskTypesAbFilter.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		isMobile = OO.ui.isMobile(),
		isSuggestedEditsActivated = mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' ),
		newcomerTaskLogger = new NewcomerTaskLogger();

	/**
	 * @param {Element} suggestedEditsModuleNode DOM node of the suggested edits module.
	 * @param {boolean} shouldLog If event logging should be used. Set to false when this method
	 *   is called from fetchTasksAndUpdateView, where the mobile summary HTML is updated when
	 *   users interact with task / topic filters.
	 */
	function loadExtraDataForSuggestedEdits( suggestedEditsModuleNode, shouldLog ) {
		// FIXME doesn't belong here; not sure what the right place would be though.
		var GrowthTasksApi = require( '../ext.growthExperiments.Homepage.SuggestedEdits/GrowthTasksApi.js' ),
			SmallTaskCard = require( '../ext.growthExperiments.Homepage.SuggestedEdits/SmallTaskCard.js' ),
			taskPreviewData = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ][ 'task-preview' ] || null,
			activationSettings = { 'growthexperiments-homepage-suggestededits-activated': 1 },
			api = new GrowthTasksApi( {
				suggestedEditsConfig: require( '../ext.growthExperiments.Homepage.SuggestedEdits/config.json' ),
				isMobile: isMobile,
				logContext: 'mobilesummary'
			} );

		if ( !isSuggestedEditsActivated ) {
			// Tapping on the task card should be considered enough to activate the module, with no
			// further onboarding dialogs shown.
			$( suggestedEditsModuleNode ).on( 'click', function () {
				new mw.Api().saveOptions( activationSettings ).then( function () {
					mw.user.options.set( activationSettings );
				} );
				// Set state to activated so that HomepageLogger uses correct value for
				// subsequent log events.
				mw.config.set( 'wgGEHomepageModuleState-suggested-edits', 'activated' );
			} );
		}

		if ( taskPreviewData && taskPreviewData.title ) {
			api.getExtraDataFromPcs( taskPreviewData ).then( function ( task ) {
				var previewTask, taskCard;

				// Hide the pageview count in the preview card.
				previewTask = $.extend( {}, task );
				previewTask.pageviews = null;

				taskCard = new SmallTaskCard( {
					task: previewTask,
					taskTypes: TaskTypesAbFilter.getTaskTypes(),
					taskUrl: null
				} );

				$( suggestedEditsModuleNode ).find( '.mw-ge-small-task-card' )
					.replaceWith( taskCard.$element );

				if ( shouldLog ) {
					newcomerTaskLogger.log( task, 0 );
					homepageModuleLogger.log( 'suggested-edits', 'mobile-summary', 'se-task-impression',
						{ newcomerTaskToken: task.token } );
				}
			}, function ( jqXHR, textStatus, errorThrown ) {
				// Error loading the task
				if ( shouldLog ) {
					homepageModuleLogger.log( 'suggested-edits', 'mobile-summary', 'se-task-pseudo-impression',
						{ type: 'error', errorMessage: textStatus + ' ' + errorThrown } );
				}
			} );
		} else if ( taskPreviewData && taskPreviewData.error ) {
			// Error loading the task, on the server side
			if ( shouldLog ) {
				homepageModuleLogger.log( 'suggested-edits', 'mobile-summary', 'se-task-pseudo-impression',
					{ type: 'error', errorMessage: taskPreviewData.error } );
			}
		}
	}

	/**
	 * Remove query params before the mobile modules are initialized.
	 * This is so that query params that shouldn't stick around do not persist when routing
	 * between the homepage and the mobile overlay via mediawiki.router.
	 */
	function beforeMobileInit() {
		var uri = new mw.Uri(),
			query = uri.query || {};
		if ( !query.overlay && !query.source ) {
			return;
		}
		var Utils = require( '../utils/Utils.js' );
		Utils.removeQueryParam( uri, [ 'overlay', 'source' ], true );
	}

	if ( mw.loader.getState( 'mobile.init' ) ) {
		beforeMobileInit();

		mw.loader.using( 'mobile.init' ).done( function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			var $summaryModulesContainer = $( '.growthexperiments-homepage-container' ),
				summaryModulesSelector = '> a > .growthexperiments-homepage-module',
				summaryModulesOverlayLinksSelector = '[data-overlay-route]',
				$summaryModules = $summaryModulesContainer.find( summaryModulesSelector ),
				// eslint-disable-next-line no-jquery/no-global-selector
				$overlayModules = $( '.growthexperiments-homepage-overlay-container' ),
				MobileOverlay = require( './MobileOverlay.js' ),
				OverlayManager = mw.mobileFrontend.require( 'mobile.startup' ).OverlayManager,
				router = require( 'mediawiki.router' ),
				overlayManager = OverlayManager.getSingleton(),
				lazyLoadModules = [],
				overlays = {},
				currentModule = null,
				// Matches routes like /homepage/moduleName or /homepage/moduleName/action
				routeRegex = /^\/homepage\/([^/]+)(?:\/([^/]+))?$/,
				Drawer = mw.mobileFrontend.require( 'mobile.startup' ).Drawer,
				Anchor = mw.mobileFrontend.require( 'mobile.startup' ).Anchor;

			/**
			 * Extract module detail HTML, heading and RL modules config var.
			 *
			 * @param {string} moduleName
			 * @return {Object}
			 */
			function getModuleData( moduleName ) {
				var data = mw.config.get( 'homepagemodules' )[ moduleName ];
				data.$overlay = $overlayModules.find( '[data-module-name="' + moduleName + '"]' );
				return data;
			}

			function getSubmodules( moduleName ) {
				// HACK: extract submodule info from the module HTML
				return $( getModuleData( moduleName ).overlay )
					.find( '.growthexperiments-homepage-module' )
					.toArray()
					.map( function ( moduleElement ) {
						return $( moduleElement ).data( 'module-name' );
					} )
					// With the current HTML structure, this shouldn't return the module itself,
					// but filter it out just to be sure
					.filter( function ( submodule ) {
						return submodule !== moduleName;
					} );
			}

			function handleRouteChange( path ) {
				var matches = path.match( routeRegex ),
					newModule = matches ? matches[ 1 ] : null;

				// Log mobile-overlay open/close when navigating to / away from a module
				// We can't do this in a show/hide event handler on the overlay itself, because it
				// gets hidden then shown again when opening and closing the question dialog
				// (due to navigation from #/homepage/moduleName to #homepage/moduleName/question)
				if ( newModule !== currentModule ) {
					// Log null -> module as opening (impression) and module -> null as closing.
					// Log module -> another module as opening but not closing, since there is
					// no closing intent on the part of the user in that case.
					if ( newModule === null ) {
						// Navigating away from a module: log closing the overlay
						homepageModuleLogger.log( currentModule, 'mobile-overlay', 'close' );
					} else {
						// Navigating to a module: log impression
						homepageModuleLogger.log( newModule, 'mobile-overlay', 'impression' );

						// Find submodules in the new module, and log impressions for them
						getSubmodules( newModule ).forEach( function ( submodule ) {
							homepageModuleLogger.log( submodule, 'mobile-overlay', 'impression' );
						} );
					}
				}

				currentModule = newModule;
			}

			overlayManager.add( routeRegex, function ( moduleName ) {
				var moduleData;
				if ( overlays[ moduleName ] === undefined ) {
					moduleData = getModuleData( moduleName );
					overlays[ moduleName ] = new MobileOverlay( {
						moduleName: moduleName,
						html: moduleData.$overlay,
						rlModules: moduleData.rlModules,
						heading: moduleData.heading
					} );
				}
				return overlays[ moduleName ];
			} );

			router.on( 'route', function ( ev ) {
				handleRouteChange( ev.path );
			} );

			// Initialize state for handleRouteChange, and log initial impression if needed
			handleRouteChange( router.getPath() );

			/**
			 * Show welcome drawer for users who haven't already seen it.
			 */
			function maybeShowWelcomeDrawer() {
				function setPreventScrolling( shouldPreventScrolling ) {
					if ( shouldPreventScrolling ) {
						document.body.classList.add( 'stop-scrolling' );
					} else {
						document.body.classList.remove( 'stop-scrolling' );
					}
				}
				// Even though this drawer isn't really a tour, we reuse the preference
				// set on desktop since if the user has seen the tour on desktop they
				// should not see the drawer on mobile, and vice versa.
				var welcomeNoticeSeenPreference = 'growthexperiments-tour-homepage-welcome',
					buttonClicked = false,
					markAsSeen = function () {
						new mw.Api().saveOption( welcomeNoticeSeenPreference, 1 );
					},
					// FIXME: in a follow-up, convert these messages to something besides variant
					//   C/D, e.g. "se-activated" / "se-unactivated"
					variantKey = isSuggestedEditsActivated ? 'd' : 'c',
					welcomeDrawer;

				if ( mw.user.options.get( welcomeNoticeSeenPreference ) ) {
					return;
				}

				welcomeDrawer = new Drawer( {
					className: 'homepage-welcome-notice',
					showCollapseIcon: false,
					children: [
						$( '<main>' )
							.append(
								$( '<h4>' ).text( mw.message( 'growthexperiments-homepage-welcome-notice-header' )
									.params( [ mw.user ] )
									.text() ),
								// The following messages are used here:
								// * growthexperiments-homepage-welcome-notice-body-variant-c
								// * growthexperiments-homepage-welcome-notice-body-variant-d
								$( '<p>' ).html( mw.message(
									'growthexperiments-homepage-welcome-notice-body-variant-' +
									variantKey
								).params( [ mw.user ] )
									.parse() ),
								$( '<footer>' )
									.addClass( 'growthexperiments-homepage-welcome-notice-footer' )
									.append(
										new Anchor( {
											href: '#',
											additionalClassNames: 'growthexperiments-homepage-welcome-notice-button',
											progressive: true,
											// The following messages are used here:
											// * growthexperiments-homepage-welcome-notice-button-text-variant-c
											// * growthexperiments-homepage-welcome-notice-button-text-variant-d
											label: mw.msg(
												'growthexperiments-homepage-welcome-notice-button-text-variant-' +
												variantKey
											)
										} ).$el
									)
							)
					],
					onBeforeHide: function () {
						markAsSeen();
						setPreventScrolling( false );
						if ( !buttonClicked ) {
							homepageModuleLogger.log( 'generic', 'mobile-summary', 'welcome-close',
								{ type: 'outside-click' } );
						}
					}
				} );
				document.body.appendChild( welcomeDrawer.$el[ 0 ] );
				welcomeDrawer.$el.find( '.homepage-welcome-notice' ).on( 'click', function () {
					buttonClicked = true;
					markAsSeen();
					homepageModuleLogger.log( 'generic', 'mobile-summary', 'welcome-close', {
						type: 'button'
					} );
					// Launch the start editing dialog for mobile users who haven't activated
					// the module yet.
					// TODO: We should probably use mw.hook instead of mw.track/trackSubscribe
					if ( isMobile && !isSuggestedEditsActivated ) {
						mw.track( 'growthexperiments.startediting', {
							// The welcome drawer doesn't belong to any module
							moduleName: 'generic',
							trigger: 'welcome'
						} );
					}
					welcomeDrawer.hide();
					setPreventScrolling( false );
				} );
				welcomeDrawer.show().then( setPreventScrolling.bind( null, true ) );
				homepageModuleLogger.log( 'generic', 'mobile-summary', 'welcome-impression' );
			}

			// Respond to mobile summary HTML loading
			mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.suggested-edits' )
				.add( maybeShowWelcomeDrawer );
			mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.start-startediting' )
				.add( maybeShowWelcomeDrawer );

			$summaryModulesContainer.on( 'click', summaryModulesOverlayLinksSelector, function ( e ) {
				e.preventDefault();
				// See BaseModule->getModuleRoute()
				if ( $( this ).data( 'overlay-route' ) ) {
					router.navigate( $( this ).data( 'overlay-route' ) );
				}
			} );

			// When the suggested edits module is present finish loading the task preview card.
			// FIXME doesn't belong here; not sure what the right place would be though.
			$summaryModules.filter( '.growthexperiments-homepage-module-suggested-edits' )
				.each( function ( i, module ) {
					loadExtraDataForSuggestedEdits( module, true );
				} );

			// Start loading the ResourceLoader modules so that tapping on one will load
			// instantly. We don't load these with page delivery so as to speed up the
			// initial page load.
			$summaryModules.each( function () {
				var moduleName = $( this ).data( 'module-name' ),
					rlModules = getModuleData( moduleName ).rlModules;
				if ( rlModules ) {
					lazyLoadModules = lazyLoadModules.concat( rlModules );
				}
			} );

			$summaryModules.each( function () {
				/**
				 * Fired after homepage module summary content is rendered on the page.
				 *
				 * The hook name is constructed with the module name, e.g.
				 * growthExperiments.mobileHomepageSummaryHtmlLoaded.start-startediting,
				 * growthExperiments.mobileHomepageSummaryHtmlLoaded.impact
				 * etc.
				 *
				 * @param {jQuery} $content The content of the homepage summary module.
				 */
				mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.' + $( this ).data( 'module-name' ) ).fire(
					$( this )
				);
			} );

			setTimeout( function () {
				mw.loader.load( lazyLoadModules );
			}, 250 );
		} );
	}

	module.exports = {
		loadExtraDataForSuggestedEdits: loadExtraDataForSuggestedEdits
	};
}() );
