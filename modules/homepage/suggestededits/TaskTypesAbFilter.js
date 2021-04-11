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
	 * @return {boolean}
	 */
	function areLinkRecommendationsEnabled() {
		var config = require( './config.json' ),
			Utils = require( '../../utils/ext.growthExperiments.Utils.js' );
		return config.GELinkRecommendationsEnabled &&
			Utils.isUserInVariant( [ 'linkrecommendation' ] );
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
		return taskTypes;
	}

	/**
	 * Convert task types which the user is not supposed to see, given the link recommendation
	 * configuration, to the closest task type available to them.
	 * @param {Array<string>} taskTypes
	 */
	function convertTaskTypes( taskTypes ) {
		var linkRecommendationsEnabled = areLinkRecommendationsEnabled();
		taskTypes = taskTypes.map( function ( taskType ) {
			if ( linkRecommendationsEnabled && taskType === 'links' ) {
				return 'link-recommendation';
			} else if ( !linkRecommendationsEnabled && taskType === 'link-recommendation' ) {
				return 'links';
			} else {
				return taskType;
			}
		} );
		// filter duplicates
		taskTypes = taskTypes.filter( function ( element, index, self ) {
			return index === self.indexOf( element );
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
