( function () {
	'use strict';

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

		if ( taskPreviewData ) {
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
			} );
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
				Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
				logger = new Logger(
					mw.config.get( 'wgGEHomepageLoggingEnabled' ),
					mw.config.get( 'wgGEHomepagePageviewToken' )
				),
				router = require( 'mediawiki.router' ),
				overlayManager = OverlayManager.getSingleton(),
				lazyLoadModules = [],
				overlays = {},
				currentModule = null,
				// Matches routes like /homepage/moduleName or /homepage/moduleName/action
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
				if ( newModule !== currentModule ) {
					// Log null -> module as opening (impression) and module -> null as closing.
					// Log module -> another module as opening but not closing, since there is
					// no closing intent on the part of the user in that case.
					if ( newModule === null ) {
						// Navigating away from a module: log closing the overlay
						logger.log( currentModule, 'mobile-overlay', 'close' );
					} else {
						// Navigating to a module: log impression
						logger.log( newModule, 'mobile-overlay', 'impression' );

						// Find submodules in the new module, and log impressions for them
						getSubmodules( newModule ).forEach( function ( submodule ) {
							logger.log( submodule, 'mobile-overlay', 'impression' );
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
