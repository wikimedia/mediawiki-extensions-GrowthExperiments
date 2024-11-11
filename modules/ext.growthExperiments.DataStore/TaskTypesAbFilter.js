/**
 * This is a temporary hack for filtering task types for an A/B test, per T278123.
 * This file is shared between multiple modules that handle task types. Dependencies:
 * - HomepageHooks::getSuggestedEditsConfigJson() as ./config.json
 * - HomepageHooks::getTaskTypesJson() as ./TaskTypes.json
 * - HomepageHooks::getDefaultTaskTypesJson() as ./DefaultTaskTypes.json
 */
( function () {
	const OLD_LINK_TASK_TYPE = 'links',
		LINK_RECOMMENDATION_TASK_TYPE = 'link-recommendation',
		IMAGE_RECOMMENDATION_TASK_TYPE = 'image-recommendation',
		SECTION_IMAGE_RECOMMENDATION_TASK_TYPE = 'section-image-recommendation';

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
			LINK_RECOMMENDATION_TASK_TYPE in taskTypes &&
			ge.utils.getUserVariant() !== 'no-link-recommendation';
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
		} );
		return taskTypes;
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
		return taskTypeIds.map( ( taskTypeId ) => ( taskTypeId in map ) ? map[ taskTypeId ] : taskTypeId ).filter(
			// filter duplicates and false
			( element, index, self ) => element !== false && index === self.indexOf( element )
		);
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
		getDefaultTaskTypes: getDefaultTaskTypes
	};
}() );
