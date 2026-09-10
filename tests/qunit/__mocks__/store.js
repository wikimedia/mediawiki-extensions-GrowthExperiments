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
		// The mocked user is in the control group, so the filters are topic based.
		interestsEnabled: false,
		getFiltersQuery() {
			return this.getTopicsQuery();
		},
		selectsInterests() {
			return false;
		},
		getSelectedTaskTypes() {},
		getSelectedTopics() {},
		getSelectedInterests() {
			return [];
		},
		setSelectedInterests() {}
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
