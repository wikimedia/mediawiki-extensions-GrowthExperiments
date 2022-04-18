'use strict';
/**
 * Entity representing the possible topic filters to apply
 * to the growth tasks API search
 *
 * @class mw.libs.ge.TopicFilters
 * @constructor
 * @param {Object} [config]
 * @param {string[]} [config.topics] A list of topic ID's
 * @param {string} [config.topicsMatchMode] The topic match mode to use.
 * One of ( 'OR', 'AND' )
 * @see TOPIC_MATCH_MODES
 */
function TopicFilters( config ) {
	config = config || {};
	this.topics = config.topics || [];
	this.topicsMatchMode = config.topicsMatchMode || null;
}
/**
 * Will return true if there aren't any topic IDs
 * set in the filter
 *
 * @return {boolean}
 */
TopicFilters.prototype.hasFilters = function () {
	return this.topics.length > 0;
};

/**
 * Return the current list of topics ID's set
 * in the filter
 *
 * @return {string[]}
 */
TopicFilters.prototype.getTopics = function () {
	return this.topics;
};

/**
 * Return the the current set topic match mode
 *
 * @return {string} One of ( 'OR', 'AND' )
 * @see TOPIC_MATCH_MODES
 */
TopicFilters.prototype.getTopicsMatchMode = function () {
	return this.topicsMatchMode;
};

module.exports = TopicFilters;
