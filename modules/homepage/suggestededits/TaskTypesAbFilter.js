/**
 * This is a temporary hack for filtering task types for an A/B test, per T278123.
 * This file is shared between multiple modules that handle task types. Dependencies:
 * - ext.growthExperiments.Util.js
 * - HomepageHooks::getSuggestedEditsConfigJson() as ./config.json
 */
( function () {
	/**
	 * Check whether link recommendations are enabled for the current user.
	 *
	 * This is the equivalent of NewcomerTasksUserOptionsLookup::areLinkRecommendationsEnabled().
	 *
	 * @return {boolean}
	 */
	function areLinkRecommendationsEnabled() {
		var config = require( './config.json' ),
			Utils = require( '../../utils/ext.growthExperiments.Utils.js' );
		return config.GELinkRecommendationsEnabled &&
			Utils.isUserInVariant( [ 'linkrecommendation' ] );
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
			Utils = require( '../../utils/ext.growthExperiments.Utils.js' );
		return config.GEImageRecommendationsEnabled &&
			Utils.isUserInVariant( [ 'imagerecommendation' ] );
	}

	/**
	 * Remove task types the current user should not see.
	 *
	 * @param {Object} taskTypes Task type ID => task data, typically from loading TaskTypes.json
	 * @return {Object} The same task type data, without the task types the user shouldn't see.
	 */
	function filterTaskTypes( taskTypes ) {
		// Abort if task types couldn't be loaded.
		if ( !( taskTypes instanceof Object ) || '_error' in taskTypes ) {
			return taskTypes;
		}

		taskTypes = $.extend( {}, taskTypes );
		if ( areLinkRecommendationsEnabled() ) {
			delete taskTypes.links;
		} else {
			delete taskTypes[ 'link-recommendation' ];
		}
		if ( !areImageRecommendationsEnabled() ) {
			delete taskTypes[ 'image-recommendation' ];
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
			if ( linkRecommendationsEnabled && taskType === 'links' ) {
				return 'link-recommendation';
			} else if ( !linkRecommendationsEnabled && taskType === 'link-recommendation' ) {
				return 'links';
			} else if ( !imageRecommendationsEnabled && taskType === 'image-recommendation' ) {
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
	 * @param {string[]} defaultDefaultTaskTypes "Normal" default task types from
	 *   DefaultTaskTypes.json.
	 * @return {string[]}
	 */
	function filterDefaultTaskTypes( defaultDefaultTaskTypes ) {
		if ( areLinkRecommendationsEnabled() ) {
			return [ 'link-recommendation' ];
		} else if ( areImageRecommendationsEnabled() ) {
			return [ 'image-recommendation' ];
		} else {
			return defaultDefaultTaskTypes;
		}
	}

	module.exports = {
		filterTaskTypes: filterTaskTypes,
		convertTaskTypes: convertTaskTypes,
		filterDefaultTaskTypes: filterDefaultTaskTypes
	};
}() );
