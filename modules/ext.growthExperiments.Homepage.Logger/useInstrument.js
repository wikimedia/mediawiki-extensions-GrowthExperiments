'use strict';

/**
 * Get experiment data for web/base schema from TestKitchen.
 * Supports multi-experiment setup.
 *
 * Returns null when the user is not enrolled in any GrowthExperiments experiment
 * Omit experiment data for unenrolled users.
 *
 * @return {Object|null} { coordinator, assigned, enrolled, other_assigned? } or null
 */
function getExperimentDataForSchema() {
	const tkConfig = mw.config.get( 'wgTestKitchenUserExperiments' );
	if ( !tkConfig || !tkConfig.active_experiments ) {
		return null;
	}

	const growthExperiments = tkConfig.active_experiments.filter(
		( exp ) => ( typeof exp === 'string' ? exp : exp.name ).startsWith( 'growthexperiments' ),
	);
	if ( growthExperiments.length === 0 ) {
		return null;
	}

	const first = growthExperiments[ 0 ];
	const firstName = typeof first === 'string' ? first : first.name;
	const firstExp = mw.testKitchen.getExperiment( firstName );
	if ( !firstExp ) {
		return null;
	}

	const assignedRaw = firstExp.getAssignedGroup();
	if ( typeof assignedRaw !== 'string' ) {
		return null;
	}

	const coordinator = typeof first === 'object' && first.config ? first.config.coordinator : 'custom';
	const enrolled = typeof first === 'object' && first.config ? first.config.enrolled : firstName;
	const assigned = assignedRaw;

	// eslint-disable-next-line camelcase
	const other_assigned = {};
	for ( let i = 1; i < growthExperiments.length; i++ ) {
		const exp = growthExperiments[ i ];
		const expName = typeof exp === 'string' ? exp : exp.name;
		const expInstance = mw.testKitchen.getExperiment( expName );
		if ( expInstance ) {
			const variant = expInstance.getAssignedGroup();
			if ( typeof variant === 'string' ) {
				// eslint-disable-next-line camelcase
				other_assigned[ expName ] = variant;
			}
		}
	}

	const result = { coordinator, assigned, enrolled };
	if ( Object.keys( other_assigned ).length > 0 ) {
		// eslint-disable-next-line camelcase
		result.other_assigned = other_assigned;
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
