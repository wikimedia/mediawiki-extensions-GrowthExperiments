( function () {
	'use strict';

	var HomepageModuleLogger = require( 'ext.growthExperiments.Homepage.Logger' ),
		NewcomerTaskLogger = require( './suggestededits/ext.growthExperiments.NewcomerTaskLogger.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		newcomerTaskLogger = new NewcomerTaskLogger();

	/**
	 * @param {Element} suggestedEditsModuleNode DOM node of the suggested edits module.
	 */
	function loadExtraDataForSuggestedEdits( suggestedEditsModuleNode ) {
		// FIXME doesn't belong here; not sure what the right place would be though.
		var GrowthTasksApi = require( './suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
			SmallTaskCard = require( './suggestededits/ext.growthExperiments.SuggestedEdits.SmallTaskCard.js' ),
			taskPreviewData = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ][ 'task-preview' ] || null,
			api = new GrowthTasksApi( {
				suggestedEditsConfig: require( './config.json' ),
				isMobile: OO.ui.isMobile(),
				logContext: 'mobilesummary'
			} );

		if ( taskPreviewData && taskPreviewData.title ) {
			api.getExtraDataFromPcs( taskPreviewData ).then( function ( task ) {
				var previewTask, taskCard;

				// Hide the pageview count in the preview card.
				previewTask = $.extend( {}, task );
				previewTask.pageviews = null;
				taskCard = new SmallTaskCard( {
					task: previewTask,
					taskTypes: require( './TaskTypes.json' ),
					taskUrl: null
				} );
				$( suggestedEditsModuleNode ).find( '.mw-ge-small-task-card' )
					.replaceWith( taskCard.$element );

				homepageModuleLogger.log( 'suggested-edits', 'mobile-summary', 'se-task-impression',
					{ newcomerTaskToken: newcomerTaskLogger.log( task, 0 ) } );
			}, function ( jqXHR, textStatus, errorThrown ) {
				// Error loading the task
				homepageModuleLogger.log( 'suggested-edits', 'mobile-summary', 'se-task-pseudo-impression',
					{ type: 'error', errorMessage: textStatus + ' ' + errorThrown } );
			} );
		} else if ( taskPreviewData && taskPreviewData.error ) {
			// Error loading the task, on the server side
			homepageModuleLogger.log( 'suggested-edits', 'mobile-summary', 'se-task-pseudo-impression',
				{ type: 'error', errorMessage: taskPreviewData.error } );
		}
	}

	if ( mw.loader.getState( 'mobile.init' ) ) {
		mw.loader.using( 'mobile.init' ).done( function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			var $summaryModulesContainer = $( '.growthexperiments-homepage-container' ),
				summaryModulesSelector = '> a > .growthexperiments-homepage-module',
				$summaryModules = $summaryModulesContainer.find( summaryModulesSelector ),
				// eslint-disable-next-line no-jquery/no-global-selector
				$overlayModules = $( '.growthexperiments-homepage-overlay-container' ),
				MobileOverlay = require( './ext.growthExperiments.Homepage.MobileOverlay.js' ),
				OverlayManager = mw.mobileFrontend.require( 'mobile.startup' ).OverlayManager,
				router = require( 'mediawiki.router' ),
				overlayManager = OverlayManager.getSingleton(),
				lazyLoadModules = [],
				overlays = {},
				currentModule = null,
				// Matches routes like /homepage/moduleName or /homepage/moduleName/action
				routeRegex = /^\/homepage\/([^/]+)(?:\/([^/]+))?$/,
				Utils = require( '../../utils/ext.growthExperiments.Utils.js' ),
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
			 * Show welcome drawer for users in variant C and D who haven't already seen it.
			 */
			function maybeShowWelcomeDrawer() {
				// Even though this drawer isn't really a tour, we reuse the preference
				// set on desktop since if the user has seen the tour on desktop they
				// should not see the drawer on mobile, and vice versa.
				var welcomeNoticeSeenPreference = 'growthexperiments-tour-homepage-welcome',
					buttonClicked = false,
					markAsSeen = function () {
						new mw.Api().saveOption( welcomeNoticeSeenPreference, 1 );
					},
					welcomeDrawer;
				if ( !Utils.isUserInVariant( [ 'C', 'D' ] ) ||
					mw.user.options.get( welcomeNoticeSeenPreference ) ) {
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
									Utils.getUserVariant().toLowerCase()
								).params( [ mw.user ] )
									.parse() ),
								$( '<footer>' ).addClass( 'growthexperiments-homepage-welcome-notice-footer' )
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
												Utils.getUserVariant().toLowerCase()
											)
										} ).$el
									)
							)
					],
					onBeforeHide: function () {
						markAsSeen();
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
					homepageModuleLogger.log( 'generic', 'mobile-summary', 'welcome-close', { type: 'button' } );
					// Launch the start editing dialog for variant C users.
					// TODO: We should probably use mw.hook instead of mw.track/trackSubscribe here
					if ( Utils.isUserInVariant( [ 'C' ] ) ) {
						mw.track( 'growthexperiments.startediting' );
					}
					welcomeDrawer.hide();
				} );
				welcomeDrawer.show();
				homepageModuleLogger.log( 'generic', 'mobile-summary', 'welcome-impression' );
			}

			// Respond to mobile summary HTML loading
			mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.suggested-edits' )
				.add( maybeShowWelcomeDrawer );
			mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.start-startediting' )
				.add( maybeShowWelcomeDrawer );

			$summaryModulesContainer.on( 'click', summaryModulesSelector, function ( e ) {
				e.preventDefault();
				// See BaseModule->getModuleRoute()
				if ( $( this ).data( 'module-route' ) ) {
					router.navigate( $( this ).data( 'module-route' ) );
				}
			} );

			// When the suggested edits module is present and we are in the right variant,
			// finish loading the task preview card.
			// FIXME doesn't belong here; not sure what the right place would be though.
			$summaryModules.filter( '.growthexperiments-homepage-module-suggested-edits' )
				.filter( '.growthexperiments-homepage-module-user-variant-C,' +
					'.growthexperiments-homepage-module-user-variant-D' )
				.each( function ( i, module ) {
					loadExtraDataForSuggestedEdits( module );
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
