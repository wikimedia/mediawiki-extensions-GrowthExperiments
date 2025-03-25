const mobile = require( 'mobile.startup' );
( function () {
	'use strict';
	const HomepageModuleLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		NewcomerTaskLogger = require( '../ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
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
		Drawer = mobile.Drawer;

	/**
	 * Remove query params before the mobile modules are initialized.
	 * This is so that query params that shouldn't stick around do not persist when routing
	 * between the homepage and the mobile overlay via mediawiki.router.
	 */
	function beforeMobileInit() {
		const url = new URL( window.location.href );
		if ( !url.searchParams.has( 'overlay' ) && !url.searchParams.has( 'source' ) ) {
			return;
		}
		const Utils = require( '../utils/Utils.js' );
		Utils.removeQueryParam( url, [ 'overlay', 'source' ] );
	}

	/**
	 * Set up mobile overlays for each mobile summary module
	 */
	function onMobileInit() {
		const MobileOverlay = require( './MobileOverlay.js' ),
			router = require( 'mediawiki.router' ),
			overlayManager = mobile.getOverlayManager(),
			lazyLoadModules = [],
			overlays = {},
			// Matches routes like /homepage/moduleName or /homepage/moduleName/action
			// FIXME or describe why it is okay
			routeRegex = /^\/homepage\/([^/]+)(?:\/([^/]+))?$/;
		let currentModule = null;

		/**
		 * Extract module detail HTML, heading and RL modules config var.
		 *
		 * @param {string} moduleName
		 * @return {Object}
		 */
		function getModuleData( moduleName ) {
			const data = mw.config.get( 'homepagemodules' )[ moduleName ];
			data.$overlay = $overlayModules.find( '[data-module-name="' + moduleName + '"]' );
			return data;
		}

		function getSubmodules( moduleName ) {
			// HACK: extract submodule info from the module HTML
			return $( getModuleData( moduleName ).overlay )
				.find( '.growthexperiments-homepage-module' )
				.toArray()
				.map( ( moduleElement ) => $( moduleElement ).data( 'module-name' ) )
				// With the current HTML structure, this shouldn't return the module itself,
				// but filter it out just to be sure
				.filter( ( submodule ) => submodule !== moduleName );
		}

		function handleRouteChange( path ) {
			const matches = path.match( routeRegex ),
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
					getSubmodules( newModule ).forEach( ( submodule ) => {
						homepageModuleLogger.log( submodule, 'mobile-overlay', 'impression' );
					} );
				}
			}

			currentModule = newModule;
		}

		overlayManager.add( routeRegex, ( moduleName ) => {
			if ( overlays[ moduleName ] === undefined ) {
				const moduleData = getModuleData( moduleName );
				const overlay = MobileOverlay.make( {
					moduleName: moduleName,
					html: moduleData.$overlay,
					rlModules: moduleData.rlModules,
					heading: moduleData.heading
				} );
				overlays[ moduleName ] = overlay;

				// Fire an event when the overlay closes that other modules can react to the closing
				overlay.on( 'hide', () => {
					mw.hook( 'growthExperiments.mobileOverlayClosed.' + moduleName ).fire();
				} );
			}
			return overlays[ moduleName ];
		} );

		router.on( 'route', ( ev ) => {
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
			const moduleName = $( this ).data( 'module-name' ),
				rlModules = getModuleData( moduleName ).rlModules;
			if ( rlModules ) {
				lazyLoadModules.push( ...rlModules );
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

		setTimeout( () => {
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
		const welcomeNoticeSeenPreference = 'growthexperiments-tour-homepage-welcome',
			markAsSeen = function () {
				new mw.Api().saveOption( welcomeNoticeSeenPreference, 1 );
			},
			// FIXME: in a follow-up, convert these messages to something besides variant
			//   C/D, e.g. "se-activated" / "se-unactivated"
			variantKey = isSuggestedEditsActivated ? 'd' : 'c';
		let buttonClicked = false;

		if ( mw.user.options.get( welcomeNoticeSeenPreference ) ) {
			return;
		}

		const welcomeDrawer = new Drawer( {
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
								$( '<a>' ).attr( {
									href: '#',
									class: 'growthexperiments-homepage-welcome-notice-button'
								} ).text(
									// The following messages are used here:
									// * growthexperiments-homepage-welcome-notice-button-text-variant-c
									// * growthexperiments-homepage-welcome-notice-button-text-variant-d
									mw.msg(
										'growthexperiments-homepage-welcome-notice-button-text-variant-' +
										variantKey
									)
								)
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
		welcomeDrawer.$el.find( '.homepage-welcome-notice' ).on( 'click', () => {
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
	 * Infuse suggested edits mobile summary module with SuggestedEditsMobileSummary and set up
	 * event handlers for updating the module and showing the welcome drawer
	 */
	function setUpSuggestedEdits() {
		$summaryModules.filter( '.growthexperiments-homepage-module-suggested-edits' )
			.each( ( i, module ) => {
				const suggestedEditsMobileSummary = new SuggestedEditsMobileSummary( {
					$element: $( module ),
					newcomerTaskLogger: newcomerTaskLogger,
					homepageModuleLogger: homepageModuleLogger
				}, rootStore );

				suggestedEditsMobileSummary.initialize();
				if ( !isSuggestedEditsActivated ) {
					suggestedEditsMobileSummary.enableSuggestedEditsActivation();
				}

				// Update the suggested edits module on the homepage when the overlay closes
				mw.hook( 'growthExperiments.mobileOverlayClosed.suggested-edits' ).add( () => {
					suggestedEditsMobileSummary.updateUiBasedOnState();
				} );
			} );

		// Respond to mobile summary HTML loading
		mw.hook( 'growthExperiments.mobileHomepageSummaryHtmlLoaded.suggested-edits' )
			.add( maybeShowWelcomeDrawer );
	}

	if ( mw.loader.getState( 'mobile.init' ) ) {
		beforeMobileInit();

		mw.loader.using( 'mobile.init' ).done( () => {
			onMobileInit();
		} );
	}
	setUpSuggestedEdits();
}() );
