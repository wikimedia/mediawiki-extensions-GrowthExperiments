/**
 * This is a temporary hack for filtering task types for an A/B test, per T278123.
 * This file is shared between multiple modules that handle task types. Dependencies:
 * - HomepageHooks::getSuggestedEditsConfigJson() as ./config.json
 * - HomepageHooks::getTaskTypesJson() as ./TaskTypes.json
 * - HomepageHooks::getDefaultTaskTypesJson() as ./DefaultTaskTypes.json
 * - ../utils/Utils.js
 */
( function () {
	const OLD_LINK_TASK_TYPE = 'links',
		LINK_RECOMMENDATION_TASK_TYPE = 'link-recommendation',
		IMAGE_RECOMMENDATION_TASK_TYPE = 'image-recommendation',
		SECTION_IMAGE_RECOMMENDATION_TASK_TYPE = 'section-image-recommendation',
		REVISE_TONE_TASK_TYPE = 'revise-tone';

	/**
	 * Returns the given task type if it's available, or false if it is not.
	 * Note that this doesn't check whether the user should see it, just that it exists
	 * on the wiki.
	 *
	 * @param {string} taskTypeId
	 * @return {string|false}
	 */
	function taskTypeOrFalse( taskTypeId ) {
		const taskTypes = require( './TaskTypes.json' );
		return ( taskTypeId in taskTypes ) ? taskTypeId : false;
	}

	/**
	 * Check whether link recommendations are enabled for the current user.
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::areLinkRecommendationsEnabled().
	 *
	 * @return {boolean}
	 */
	function areLinkRecommendationsEnabled() {
		const config = require( './config.json' ),
			taskTypes = require( './TaskTypes.json' );
		return config.GELinkRecommendationsEnabled &&
			LINK_RECOMMENDATION_TASK_TYPE in taskTypes;
	}

	/**
	 * Check whether image recommendations are enabled for the current user.
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::areImageRecommendationsEnabled().
	 *
	 * @return {boolean}
	 */
	function areImageRecommendationsEnabled() {
		const config = require( './config.json' ),
			taskTypes = require( './TaskTypes.json' );
		return config.GEImageRecommendationsEnabled &&
			IMAGE_RECOMMENDATION_TASK_TYPE in taskTypes;
	}

	/**
	 * Check whether section-level image recommendations are enabled for the current user.
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::areSectionImageRecommendationsEnabled().
	 *
	 * @return {boolean}
	 */
	function areSectionImageRecommendationsEnabled() {
		const config = require( './config.json' ),
			taskTypes = require( './TaskTypes.json' );
		return config.GENewcomerTasksSectionImageRecommendationsEnabled &&
			SECTION_IMAGE_RECOMMENDATION_TASK_TYPE in taskTypes;
	}

	/**
	 * Check whether revise tone recommendations are enabled for the current user.
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::areReviseToneRecommendationsEnabled().
	 *
	 * @return {boolean}
	 */
	function areReviseToneRecommendationsEnabled() {
		const config = require( './config.json' ),
			taskTypes = require( './TaskTypes.json' ),
			shouldCheckGroupAssigned = mw.config.get( 'wgGEUseTestKitchenExtension' ),
			isReviseToneEnabled = config.GEReviseToneSuggestedEditEnabled &&
				REVISE_TONE_TASK_TYPE in taskTypes;
		let assignedGroup = null;
		// Only check group assigned if experiment manager is Test Kitchen's
		// TODO: remove after experiment is concluded, T407802
		if ( mw && mw.testKitchen ) {
			assignedGroup = mw.testKitchen.getExperiment( 'growthexperiments-revise-tone' ).getAssignedGroup();
		}
		return shouldCheckGroupAssigned ?
			( assignedGroup === 'treatment' && isReviseToneEnabled ) :
			isReviseToneEnabled;
	}

	/**
	 * Get all task types, removing the ones the current user should not see.
	 *
	 * @return {Object} The same task type data, without the task types the user shouldn't see.
	 */
	function getTaskTypes() {
		const defaultTaskTypes = require( './TaskTypes.json' ),
			conversionMap = getConversionMap(),
			taskTypes = {};

		// Abort if task types couldn't be loaded.
		if ( !( defaultTaskTypes instanceof Object ) || '_error' in defaultTaskTypes ) {
			return defaultTaskTypes;
		}

		Object.keys( defaultTaskTypes ).forEach( ( taskTypeId ) => {
			if ( !( taskTypeId in conversionMap ) ) {
				taskTypes[ taskTypeId ] = defaultTaskTypes[ taskTypeId ];
			}
			if ( taskTypes[ taskTypeId ] && isTaskTypeUnavailable( taskTypeId ) ) {
				taskTypes[ taskTypeId ].disabled = true;
				taskTypes[ taskTypeId ].unavailable = true;
			}
		} );

		return taskTypes;
	}

	function isTaskTypeUnavailable( taskTypeId ) {
		const suggestedEditsTaskTypesData = getSuggestedEditsData();
		return suggestedEditsTaskTypesData &&
			suggestedEditsTaskTypesData.unavailableTaskTypes.includes( taskTypeId );
	}

	/**
	 * Get mapping of task types which the user is not supposed to see to a similar task type
	 * or false (meaning nothing should be shown instead).
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::getConversionMap().
	 *
	 * @return {Object} A map of old task type ID => new task type ID or false.
	 */
	function getConversionMap() {
		const map = {};
		if ( areLinkRecommendationsEnabled() ) {
			map[ OLD_LINK_TASK_TYPE ] = LINK_RECOMMENDATION_TASK_TYPE;
		} else {
			map[ LINK_RECOMMENDATION_TASK_TYPE ] = taskTypeOrFalse( OLD_LINK_TASK_TYPE );
		}
		if ( !areImageRecommendationsEnabled() ) {
			map[ IMAGE_RECOMMENDATION_TASK_TYPE ] = false;
		}
		if ( !areSectionImageRecommendationsEnabled() ) {
			map[ SECTION_IMAGE_RECOMMENDATION_TASK_TYPE ] = false;
		}
		if ( !areReviseToneRecommendationsEnabled() ) {
			map[ REVISE_TONE_TASK_TYPE ] = false;
		}
		return map;
	}

	/**
	 * Convert task types which the user is not supposed to see, given the user variant
	 * configuration, to the closest task type available to them.
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::convertTaskTypes().
	 *
	 * @param {Array<string>} taskTypeIds
	 * @return {Array<string>}
	 */
	function convertTaskTypes( taskTypeIds ) {
		const map = getConversionMap();
		const enabledAndAvailableTaskTypeIds = taskTypeIds.map( ( taskTypeId ) => ( taskTypeId in map ) ? map[ taskTypeId ] : taskTypeId ).filter(
			// filter duplicates and false
			( element, index, self ) => element !== false &&
					index === self.indexOf( element ) &&
					!isTaskTypeUnavailable( element ),
		);

		if ( !enabledAndAvailableTaskTypeIds.length ) {
			const suggestedEditsTaskTypesData = getSuggestedEditsData();
			if ( suggestedEditsTaskTypesData && suggestedEditsTaskTypesData.taskTypes.length ) {
				// If the suggested edits module informs tasktypes with no enabled and available
				// types for the user, assume the first one is a suggestion and add it to the types filter
				const nextSuggestedTaskTypeId = suggestedEditsTaskTypesData.taskTypes[ 0 ];
				enabledAndAvailableTaskTypeIds.push( nextSuggestedTaskTypeId );
			}
		}

		return enabledAndAvailableTaskTypeIds;
	}

	function getSuggestedEditsData() {
		// Retrieved from when in Special:Homepage and from wgGESuggestedEditsTaskTypes during
		// suggested edits sessions.
		return mw.config.get( 'wgGEHomepageModuleActionData-suggested-edits' ) ||
			mw.config.get( 'wgGESuggestedEditsTaskTypes' );
	}

	/**
	 * Get the default task types for the current user, overriding the global default as needed.
	 *
	 * @return {string[]}
	 */
	function getDefaultTaskTypes() {
		return require( './DefaultTaskTypes.json' );
	}

	module.exports = {
		getTaskTypes: getTaskTypes,
		convertTaskTypes: convertTaskTypes,
		getDefaultTaskTypes: getDefaultTaskTypes,
	};
}() );
