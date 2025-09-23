const TOPIC_MATCH_MODE_OR = 'OR';
const TOPIC_MATCH_MODE_AND = 'AND';
const TOPIC_MATCH_MODES = {
	OR: TOPIC_MATCH_MODE_OR,
	AND: TOPIC_MATCH_MODE_AND,
};
const TaskTypesAbFilter = require( './TaskTypesAbFilter.js' );

module.exports = {
	/** @property {string[]} TOPIC_MATCH_MODES Available topic match modes */
	TOPIC_MATCH_MODES: TOPIC_MATCH_MODES,
	/** @property {Object} SUGGESTED_EDITS_CONFIG Suggested edits config outputted via HomepageHooks::getSuggestedEditsConfigJson */
	SUGGESTED_EDITS_CONFIG: require( './config.json' ),
	/** @property {Object} ALL_TASK_TYPES All task types available to the user */
	ALL_TASK_TYPES: TaskTypesAbFilter.getTaskTypes(),
	/** @property {Object} DEFAULT_TASK_TYPES Default task types for the user */
	DEFAULT_TASK_TYPES: TaskTypesAbFilter.getDefaultTaskTypes(),
	/** @property {Object} TOPIC_DATA All topics available to the user */
	TOPIC_DATA: require( './Topics.js' ),
	EVENTS: {
		FILTER_SELECTION_CHANGED: 'filterSelectionChanged',
		TASK_QUEUE_CHANGED: 'taskQueueChanged',
		TASK_QUEUE_LOADING: 'taskQueueLoading',
		TASK_QUEUE_FAILED_LOADING: 'taskQueueFailedLoading',
		FETCHED_MORE_TASKS: 'fetchedMoreTasks',
		CURRENT_TASK_EXTRA_DATA_CHANGED: 'taskExtraDataChanged',
	},
};
