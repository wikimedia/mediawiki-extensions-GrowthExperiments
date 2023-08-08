( function () {
	'use strict';
	var HomepageModuleLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		isMobile = OO.ui.isMobile(),
		isSuggestedEditsActivated = mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' ),
		newcomerTaskLogger = new NewcomerTaskLogger(),
		rootStore = require( 'ext.growthExperiments.DataStore' ),
		SuggestedEditsMobileSummary = require( './SuggestedEditsMobileSummary.js' ),
		// eslint-disable-next-line no-jquery/no-global-selector
		$summaryModulesContainer = $( '.growthexperiments-homepage-container' ),
		summaryModulesSelector = '> a > .growthexperiments-homepage-module',
		summaryModulesOverlayLinksSelector = '[data-overlay-route]',
		$summaryModules = $summaryModulesContainer.find( summaryModulesSelector ),
		// eslint-disable-next-line no-jquery/no-global-selector
		$overlayModules = $( '.growthexperiments-homepage-overlay-container' ),
		Drawer = mw.mobileFrontend.require( 'mobile.startup' ).Drawer,
		Anchor = mw.mobileFrontend.require( 'mobile.startup' ).Anchor;

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

	/**
	 * Set up mobile overlays for each mobile summary module
	 */
	function onMobileInit() {
		var MobileOverlay = require( './MobileOverlay.js' ),
			OverlayManager = mw.mobileFrontend.require( 'mobile.startup' ).OverlayManager,
			router = require( 'mediawiki.router' ),
			overlayManager = OverlayManager.getSingleton(),
			lazyLoadModules = [],
			overlays = {},
			currentModule = null,
			// Matches routes like /homepage/moduleName or /homepage/moduleName/action
			// FIXME or describe why it is okay
			// eslint-disable-next-line security/detect-unsafe-regex
			routeRegex = /^\/homepage\/([^/]+)(?:\/([^/]+))?$/;

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
			// The allowed module names are defined in /analytics/legacy/homepagemodule/.
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
					if ( newModule === 'impact' && mw.config.get( 'wgGEUseNewImpactModule' ) ) {
						maybeShowNewImpactDiscovery();
					}
				}
			}

			currentModule = newModule;
		}

		overlayManager.add( routeRegex, function ( moduleName ) {
			var moduleData;
			if ( overlays[ moduleName ] === undefined ) {
				moduleData = getModuleData( moduleName );
				var overlay = new MobileOverlay( {
					moduleName: moduleName,
					html: moduleData.$overlay,
					rlModules: moduleData.rlModules,
					heading: moduleData.heading
				} );
				overlays[ moduleName ] = overlay;

				// Fire an event when the overlay closes that other modules can react to the closing
				overlay.on( 'hide', function () {
					mw.hook( 'growthExperiments.mobileOverlayClosed.' + moduleName ).fire();
				} );
			}
			return overlays[ moduleName ];
		} );

		router.on( 'route', function ( ev ) {
			handleRouteChange( ev.path );
		} );

		// Initialize state for handleRouteChange, and log initial impression if needed
		handleRouteChange( router.getPath() );

		$summaryModulesContainer.on( 'click', summaryModulesOverlayLinksSelector, function ( e ) {
			e.preventDefault();
			// See BaseModule->getModuleRoute()
			if ( $( this ).data( 'overlay-route' ) ) {
				router.navigate( $( this ).data( 'overlay-route' ) );
			}
		} );

		$summaryModules.each( function () {
			// Start loading the ResourceLoader modules so that tapping on one will load
			// instantly. We don't load these with page delivery so as to speed up the
			// initial page load.
			var moduleName = $( this ).data( 'module-name' ),
				rlModules = getModuleData( moduleName ).rlModules;
			if ( rlModules ) {
				lazyLoadModules = lazyLoadModules.concat( rlModules );
			}

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
	}

	function setPreventScrolling( shouldPreventScrolling ) {
		if ( shouldPreventScrolling ) {
			document.body.classList.add( 'stop-scrolling' );
		} else {
			document.body.classList.remove( 'stop-scrolling' );
		}
	}

	/**
	 * Show welcome drawer for users who haven't already seen it.
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

	/**
	 * Show new impact module discovery tour for users who haven't seen it.
	 */
	function maybeShowNewImpactDiscovery() {
		// Even though this drawer isn't really a tour, we reuse the preference
		// set on desktop since if the user has seen the tour on desktop they
		// should not see the drawer on mobile, and vice versa.
		var newImpactDiscoverySeen = 'growthexperiments-tour-newimpact-discovery',
			buttonClicked = false,
			markAsSeen = function () {
				new mw.Api().saveOption( newImpactDiscoverySeen, 1 );
			},
			newImpactDiscoveryDrawer;

		if ( mw.user.options.get( newImpactDiscoverySeen ) ) {
			return;
		}

		newImpactDiscoveryDrawer = new Drawer( {
			className: 'homepage-newimpact-discovery',
			showCollapseIcon: false,
			children: [
				$( '<main>' )
					.append(
						$( '<h4>' ).text( mw.message( 'growthexperiments-tour-newimpact-discovery-title' )
							.text() ),
						$( '<p>' ).text( mw.message(
							'growthexperiments-tour-newimpact-discovery-description'
						).text() ),
						$( '<footer>' )
							.addClass( 'growthexperiments-homepage-newimpact-discovery-footer' )
							.append(
								new Anchor( {
									href: '#/homepage/impact',
									progressive: true,
									label: mw.msg(
										'growthexperiments-tour-newimpact-discovery-response-button-okay'
									)
								} ).$el
							)
					)
			],
			onBeforeHide: function () {
				markAsSeen();
				setPreventScrolling( false );
				if ( !buttonClicked ) {
					homepageModuleLogger.log( 'generic', 'mobile-overlay', 'newimpactdiscovery-close',
						{ type: 'outside-click' } );
				}
			}
		} );

		$overlayModules.find( '[data-module-name="impact"]' ).append( newImpactDiscoveryDrawer.$el[ 0 ] );
		newImpactDiscoveryDrawer.$el.find( '.homepage-newimpact-discovery' ).on( 'click', function () {
			buttonClicked = true;
			// FIXME: when the click target is the Drawer surface the onBeforeHide hook is also
			// triggered making an unnecessary repeated post request to the options API
			markAsSeen();
			homepageModuleLogger.log( 'generic', 'mobile-overlay', 'newimpactdiscovery-close', {
				type: 'button'
			} );
			newImpactDiscoveryDrawer.hide();
			setPreventScrolling( false );
		} );
		newImpactDiscoveryDrawer.show().then( setPreventScrolling.bind( null, true ) );
		homepageModuleLogger.log( 'generic', 'mobile-overlay', 'newimpactdiscovery-impression' );
	}

	/**
	 * Infuse suggested edits mobile summary module with SuggestedEditsMobileSummary and set up
	 * event handlers for updating the module and showing the welcome drawer
	 */
	function setUpSuggestedEdits() {
		$summaryModules.filter( '.growthexperiments-homepage-module-suggested-edits' )
			.each( function ( i, module ) {
				var suggestedEditsMobileSummary = new SuggestedEditsMobileSummary( {
					$element: $( module ),
					newcomerTaskLogger: newcomerTaskLogger,
					homepageModuleLogger: homepageModuleLogger
				}, rootStore );

				suggestedEditsMobileSummary.initialize();
				if ( !isSuggestedEditsActivated ) {
					suggestedEditsMobileSummary.enableSuggestedEditsActivation();
				}

				// Update the suggested edits module on the homepage when the overlay closes
				mw.hook( 'growthExperiments.mobileOverlayClosed.suggested-edits' ).add( function () {
					suggestedEditsMobileSummary.updateUiBasedOnState();
				} );
			} );

		// Respond to mobile summary HTML loading
		mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.suggested-edits' )
			.add( maybeShowWelcomeDrawer );
	}

	if ( mw.loader.getState( 'mobile.init' ) ) {
		beforeMobileInit();

		mw.loader.using( 'mobile.init' ).done( function () {
			onMobileInit();
		} );
	}
	setUpSuggestedEdits();
}() );
