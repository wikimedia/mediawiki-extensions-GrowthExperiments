'use strict';
const TopicFilters = require( '../../../modules/ext.growthExperiments.DataStore/TopicFilters.js' );
const GROUPED_TOPICS = require( './GroupedTopics.json' );

/**
 * @return {mw.libs.ge.FiltersStore}
 */
const getFiltersStore = () => {
	return {
		preferences: {
			taskTypes: [ 'copyedit' ],
			topicFilters: new TopicFilters( {
				topicsMatchMode: 'OR',
				topics: []
			} )
		},
		updateStatesFromTopicsFilters() {},
		setSelectedTaskTypes() {},
		savePreferences() {},
		getGroupedTopics() {
			return GROUPED_TOPICS;
		},
		on() {},
		restoreState() {},
		backupState() {},
		getTaskTypesQuery() {},
		getTopicsQuery() {},
		getSelectedTaskTypes() {},
		getSelectedTopics() {}
	};
};

/**
 * @return {mw.libs.ge.NewcomerTasksStore}
 */
const getNewcomerTasksStore = () => {
	return {
		fetchTasks() {
			return $.Deferred().resolve();
		},
		getTaskCount() {},
		on() {},
		filters: getFiltersStore()
	};
};

module.exports = {
	newcomerTasks: getNewcomerTasksStore(),
	filters: getFiltersStore()
};
