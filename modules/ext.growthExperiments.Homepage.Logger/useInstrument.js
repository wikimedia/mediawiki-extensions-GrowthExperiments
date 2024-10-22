'use strict';
const Utils = require( '../utils/Utils.js' );

/**
 * @typedef {Object} EventLogger
 * @property {Function} logEvent
 */

/**
 * Log analytic events using Metrics Platform API.
 *
 * @param {string} streamName The name of the stream to submit events to
 * as configured in wgEventStreams.
 * @param {string} schemaId The id of the schema to validate the event.
 * @param {Object} options A map of options to configure the logger:
 *  - enabled: whether to submit an event when calling log methods.
 * @return {EventLogger}
 */
const useInstrument = ( streamName, schemaId, options = {} ) => {
	const instrument = mw.eventLog.newInstrument( streamName, schemaId );
	/**
	 * @param {string} action
	 * @param {string|null|undefined} actionSubtype
	 * @param {string|null|undefined} actionSource
	 * @param {string|null|undefined} actionContext
	 */
	const logEvent = ( action, actionSubtype, actionSource, actionContext ) => {
		if ( !options.enabled ) {
			return;
		}

		const interactionData = {
			// Fill fragment/analytics/product_metrics/experiments data
			// There's no multi experiment capability, experiments manager
			// assumes a single current experiment is always in course. Named
			// as 'growth-experiments' for the T365889 experiment.
			experiments: {
				assigned: {
					'growth-experiments': Utils.getUserVariant()
				},
				enrolled: [
					'growth-experiments'
				]
			}
		};
		if ( actionSubtype ) {
			// eslint-disable-next-line camelcase
			interactionData.action_subtype = actionSubtype;
		}

		if ( actionSource ) {
			// eslint-disable-next-line camelcase
			interactionData.action_source = actionSource;
		}

		if ( actionContext ) {
			// eslint-disable-next-line camelcase
			interactionData.action_context = actionContext;
		}
		instrument.submitInteraction( action, interactionData );
	};

	return { logEvent };
};

module.exports = useInstrument;
