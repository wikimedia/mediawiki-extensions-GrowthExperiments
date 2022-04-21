/**
 * This is a temporary hack for filtering task types for an A/B test, per T278123.
 * This file is shared between multiple modules that handle task types. Dependencies:
 * - utils/Util.js
 * - HomepageHooks::getSuggestedEditsConfigJson() as ./config.json
 * - HomepageHooks::getTaskTypesJson() as ./TaskTypes.json
 * - HomepageHooks::getDefaultTaskTypesJson() as ./DefaultTaskTypes.json
 */
( function () {
	var OLD_LINK_TASK_TYPE = 'links',
		LINK_RECOMMENDATION_TASK_TYPE = 'link-recommendation',
		IMAGE_RECOMMENDATION_TASK_TYPE = 'image-recommendation',
		IMAGE_RECOMMENDATION_VARIANT = 'imagerecommendation';

	/**
	 * Check whether the old (non-structured) link task type is available.
	 * Note that this doesn't check whether the user should see it, just that it exists
	 * on the wiki.
	 *
	 * @return {boolean}
	 */
	function doesOldLinkTaskTypeExist() {
		var taskTypes = require( './TaskTypes.json' );
		return ( OLD_LINK_TASK_TYPE in taskTypes );
	}

	/**
	 * Check whether link recommendations are enabled for the current user.
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::areLinkRecommendationsEnabled().
	 *
	 * @return {boolean}
	 */
	function areLinkRecommendationsEnabled() {
		var config = require( './config.json' ),
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
		var config = require( './config.json' ),
			taskTypes = require( './TaskTypes.json' ),
			Utils = require( '../utils/Utils.js' );
		return config.GEImageRecommendationsEnabled &&
			IMAGE_RECOMMENDATION_TASK_TYPE in taskTypes &&
			Utils.isUserInVariant( [ IMAGE_RECOMMENDATION_VARIANT ] );
	}

	/**
	 * Get all task types, removing the ones the current user should not see.
	 *
	 * @return {Object} The same task type data, without the task types the user shouldn't see.
	 */
	function getTaskTypes() {
		var taskTypes = require( './TaskTypes.json' );

		// Abort if task types couldn't be loaded.
		if ( !( taskTypes instanceof Object ) || '_error' in taskTypes ) {
			return taskTypes;
		}

		taskTypes = $.extend( {}, taskTypes );
		if ( areLinkRecommendationsEnabled() ) {
			delete taskTypes[ OLD_LINK_TASK_TYPE ];
		} else {
			delete taskTypes[ LINK_RECOMMENDATION_TASK_TYPE ];
		}
		if ( !areImageRecommendationsEnabled() ) {
			delete taskTypes[ IMAGE_RECOMMENDATION_TASK_TYPE ];
		}
		return taskTypes;
	}

	/**
	 * Convert task types which the user is not supposed to see, given the user variant
	 * configuration, to the closest task type available to them.
	 *
	 * @param {Array<string>} taskTypes
	 * @return {Array<string>}
	 */
	function convertTaskTypes( taskTypes ) {
		var linkRecommendationsEnabled = areLinkRecommendationsEnabled(),
			imageRecommendationsEnabled = areImageRecommendationsEnabled();
		taskTypes = taskTypes.map( function ( taskType ) {
			if ( linkRecommendationsEnabled && taskType === OLD_LINK_TASK_TYPE ) {
				return LINK_RECOMMENDATION_TASK_TYPE;
			} else if ( !linkRecommendationsEnabled && taskType === LINK_RECOMMENDATION_TASK_TYPE ) {
				return doesOldLinkTaskTypeExist() ? OLD_LINK_TASK_TYPE : null;
			} else if ( !imageRecommendationsEnabled && taskType === IMAGE_RECOMMENDATION_TASK_TYPE ) {
				return null;
			} else {
				return taskType;
			}
		} );
		// filter duplicates and null
		taskTypes = taskTypes.filter( function ( element, index, self ) {
			return element !== null && index === self.indexOf( element );
		} );
		return taskTypes;
	}

	/**
	 * Get the default task types for the current user, overriding the global default as needed.
	 *
	 * @return {string[]}
	 */
	function getDefaultTaskTypes() {
		var defaultDefaultTaskTypes = require( './DefaultTaskTypes.json' );
		if ( areImageRecommendationsEnabled() ) {
			return [ IMAGE_RECOMMENDATION_TASK_TYPE ];
		} else {
			return defaultDefaultTaskTypes;
		}
	}

	module.exports = {
		getTaskTypes: getTaskTypes,
		convertTaskTypes: convertTaskTypes,
		getDefaultTaskTypes: getDefaultTaskTypes
	};
}() );
