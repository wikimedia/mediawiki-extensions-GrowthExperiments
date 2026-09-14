'use strict';
/**
 * Entity representing the interest filters to apply
 * to the growth tasks API search
 *
 * Interests are prefixed article titles. They are mutually exclusive with topic filters;
 * ApiQueryGrowthTasks rejects a request carrying both, and TaskSetFilters asserts the same
 * on the server side.
 *
 * The selection is a tri-state, mirroring the gtinterests API parameter:
 *
 * - `null`: no explicit selection. The parameter is left out of the request, which makes the
 *   API filter by the interests stored for the user and serve its cached task set. This is
 *   what every ordinary fetch should use.
 * - a non-empty array: an explicit selection. The API searches for exactly that selection and
 *   bypasses its cache, so this is for previewing a selection the user has not saved yet.
 * - an empty array: also an explicit selection, asking for the unfiltered pool of suggestions.
 *
 * @class mw.libs.ge.InterestFilters
 * @constructor
 * @param {Object} [config]
 * @param {string[]|null} [config.interests] A list of prefixed article titles, or null to let
 * the API use the interests stored for the user. Longer lists are truncated to MAX_INTERESTS.
 * @see TopicFilters.js
 */
function InterestFilters( config ) {
	config = config || {};
	this.interests = Array.isArray( config.interests ) ?
		config.interests.slice( 0, mw.config.get( 'wgGENewcomerTasksMaxInterestsForQueries' ) ) :
		null;
}

/**
 * Will return true if an explicit interest selection was given, including an empty one.
 *
 * Callers use this to decide whether to send the gtinterests parameter at all, which is a
 * distinct request from sending it empty.
 *
 * @return {boolean}
 */
InterestFilters.prototype.hasSelection = function () {
	return this.interests !== null;
};

/**
 * Return the explicit interest selection, if there is one
 *
 * @return {string[]|null} A list of prefixed article titles, or null when the interests stored
 * for the user should be used instead.
 */
InterestFilters.prototype.getInterests = function () {
	return this.interests;
};

module.exports = InterestFilters;
