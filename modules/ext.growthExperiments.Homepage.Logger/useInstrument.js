'use strict';

/**
 * Get experiment data for filling the experiment fragment of web/base schema used by TestKitchen.
 * Returns null when the user is not enrolled in any experiment.
 *
 * @return {Object|null} { coordinator, assigned, enrolled, other_assigned? } or null
 */
function getExperimentDataForSchema() {
	const assignments = mw.testKitchen.getAssignments();
	const enrolledExperiments = Object.keys( assignments );
	if ( !enrolledExperiments.length ) {
		return null;
	}

	const firstExp = mw.testKitchen.compat.getExperiment( enrolledExperiments[ 0 ] );
	// OverriddenExperiment experiments do not have a config, this may be irrelevant after T414572 if
	// mw.testKitchen.getInstrument() has  notion of experiments in course
	const { coordinator, assigned, enrolled } = firstExp.config || {
		coordinator: 'forced',
		assigned: firstExp.assigned,
		enrolled: firstExp.name,
	};

	// Drop first experiment
	enrolledExperiments.shift();
	const otherAssigned = enrolledExperiments.reduce( ( acc, expName ) => {
		const experiment = mw.testKitchen.compat.getExperiment( expName );
		acc[ expName ] = experiment.getAssignedGroup();
		return acc;
	}, {} );
	const result = { coordinator, assigned, enrolled };
	if ( Object.keys( otherAssigned ).length ) {
		// eslint-disable-next-line camelcase
		result.other_assigned = otherAssigned;
	}
	return result;
}

/**
 * Get action context for web/base schema (max 320 chars).
 * Uses full actionData from wgGEHomepageModuleActionData-{moduleName} when available.
 *
 * @param {string} moduleName
 * @return {string|null}
 */
const ACTION_CONTEXT_MAX_LENGTH = 320;

function getActionContextForSchema( moduleName ) {
	const actionData = mw.config.get( 'wgGEHomepageModuleActionData-' + moduleName );
	if ( typeof actionData === 'object' && actionData !== null && !Array.isArray( actionData ) ) {
		const str = JSON.stringify( actionData );
		return str.length <= ACTION_CONTEXT_MAX_LENGTH ? str : str.slice( 0, ACTION_CONTEXT_MAX_LENGTH );
	}
	return null;
}

/**
 * @typedef {Object} EventLogger
 * @property {Function} logEvent
 * @property {Function} getActionContextForSchema
 */

/**
 * Log analytic events using TestKitchen API.
 *
 * @param {string} streamName The name of the stream to submit events to
 * as configured in wgEventStreams.
 * @param {string} schemaId The id of the schema to validate the event.
 * @return {EventLogger}
 */
const useInstrument = ( streamName, schemaId ) => {
	const instrument = mw.eventLog.newInstrument( streamName, schemaId );
	/**
	 * @param {string} action
	 * @param {string|null|undefined} actionSubtype
	 * @param {string|null|undefined} actionSource
	 * @param {string|null|undefined} actionContext
	 */
	const logEvent = ( action, actionSubtype, actionSource, actionContext ) => {
		if ( !( mw && mw.testKitchen ) ) {
			return;
		}
		const experimentData = getExperimentDataForSchema();
		const interactionData = {};
		if ( experimentData ) {
			interactionData.experiment = experimentData;
		}
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

	return { logEvent, getActionContextForSchema };
};

module.exports = useInstrument;
