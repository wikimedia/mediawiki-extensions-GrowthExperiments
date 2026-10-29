/**
 * Create the instrumentation for one module on Special:Home.
 *
 * The result is a {@link mw.testKitchen.GenericInstrumentation}: give it to `use()` of an
 * instrument or an experiment. It also has methods that send the module events.
 *
 * @param {string} moduleElementId ID of the module wrapper that PersonalDashboard renders
 * @param {string} elementFriendlyName The element_friendly_name of the module
 * @return {Function}
 */
function createHomeModuleInstrumentation( moduleElementId, elementFriendlyName ) {
	const eventSenders = [];
	// Event senders that register late must also get the earlier events.
	const recordedEvents = [];

	/**
	 * @param {mw.testKitchen.EventSenderInterface} eventSender
	 */
	const instrumentation = function ( eventSender ) {
		eventSenders.push( eventSender );
		for ( const [ action, interactionData, contextualAttributes ] of recordedEvents ) {
			eventSender.send( action, interactionData, contextualAttributes );
		}
	};

	/**
	 * @param {string} action
	 * @param {Object} [interactionData]
	 * @param {string[]} [contextualAttributes]
	 */
	instrumentation.send = ( action, interactionData = {}, contextualAttributes = [] ) => {
		for ( const eventSender of eventSenders ) {
			eventSender.send( action, interactionData, contextualAttributes );
		}
		recordedEvents.push( [ action, interactionData, contextualAttributes ] );
	};

	/**
	 * @param {string} friendlyName The element_friendly_name of the clicked element
	 * @param {?Object} [context] More data for action_context
	 */
	instrumentation.sendClick = ( friendlyName, context = null ) => {
		const interactionData = {
			// eslint-disable-next-line camelcase
			element_friendly_name: friendlyName,
		};
		if ( context !== null ) {
			// eslint-disable-next-line camelcase
			interactionData.action_context = JSON.stringify( context );
		}
		instrumentation.send( 'click', interactionData );
	};

	/**
	 * @param {Object} [context] More data for action_context
	 */
	instrumentation.sendModuleReady = ( context = {} ) => {
		instrumentation.send( 'module_ready', {
			// eslint-disable-next-line camelcase
			action_context: JSON.stringify( Object.assign(
				// eslint-disable-next-line camelcase
				{ ready_ms: Math.round( performance.now() ) },
				context,
			) ),
			// eslint-disable-next-line camelcase
			element_friendly_name: elementFriendlyName,
		} );
	};

	/**
	 * Send module_shown when the user first sees at least half of the module.
	 *
	 * @param {Object} [context] Data for action_context
	 */
	instrumentation.recordModuleShownAfterVisibleOnce = ( context ) => {
		const moduleElement = document.getElementById( moduleElementId );
		if ( !moduleElement ) {
			return;
		}
		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries[ 0 ].intersectionRatio < 0.5 ) {
				return;
			}
			const interactionData = {
				// eslint-disable-next-line camelcase
				element_friendly_name: elementFriendlyName,
			};
			if ( context ) {
				// eslint-disable-next-line camelcase
				interactionData.action_context = JSON.stringify( context );
			}
			instrumentation.send( 'module_shown', interactionData );
			observer.unobserve( moduleElement );
		}, {
			threshold: 0.5,
		} );
		observer.observe( moduleElement );
	};

	return instrumentation;
}

/**
 * Register the instrumentation with the Home health metrics instrument.
 *
 * This does nothing on other pages, when the PersonalDashboard module group override is
 * active (test traffic), or when TestKitchen is not installed.
 *
 * @param {mw.testKitchen.GenericInstrumentation} instrumentation
 */
function useHomeInstrument( instrumentation ) {
	if (
		mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Home' ||
		mw.config.get( 'wgPersonalDashboardPdoActive', false )
	) {
		return;
	}
	mw.loader.using( 'ext.testKitchen' ).then( () => {
		mw.testKitchen.getInstrument( 'home-health-metrics' ).use( instrumentation );
	}, () => {
		// TestKitchen is optional.
	} );
}

module.exports = { createHomeModuleInstrumentation, useHomeInstrument };
