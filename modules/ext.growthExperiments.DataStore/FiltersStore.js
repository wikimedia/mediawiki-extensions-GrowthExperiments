const GrowthTasksApi = require( './GrowthTasksApi.js' ),
	TaskTypesAbFilter = require( './TaskTypesAbFilter.js' ),
	aqsConfig = require( './AQSConfig.json' ),
	suggestedEditsConfig = require( './config.json' ),
	TopicFilters = require( './TopicFilters.js' ),
	CONSTANTS = require( './constants.js' ),
	TOPIC_MATCH_MODES = CONSTANTS.TOPIC_MATCH_MODES,
	TOPIC_DATA = CONSTANTS.TOPIC_DATA;

/**
 * Data store for managing suggested edits filters
 *
 * @class mw.libs.ge.FiltersStore
 * @mixes OO.EventEmitter
 * @constructor
 */
function FiltersStore() {
	OO.EventEmitter.call( this );

	// States
	/** @property {mw.libs.ge.GrowthTasksApi} api */
	this.api = new GrowthTasksApi( {
		taskTypes: TaskTypesAbFilter.getTaskTypes(),
		defaultTaskTypes: TaskTypesAbFilter.getDefaultTaskTypes(),
		suggestedEditsConfig: suggestedEditsConfig,
		aqsConfig: aqsConfig,
		isMobile: OO.ui.isMobile(),
	} );
	/** @property {Object} filtersPreference Task type and topic preferences */
	this.preferences = this.api.getPreferences();
	/** @property {Object} topicGroups Topic data formatted by group */
	this.topicGroups = this.formatTopicGroups( TOPIC_DATA );
	/** @property {boolean} topicsEnabled Whether topic selection is supported */
	this.topicsEnabled = mw.config.get( 'GEHomepageSuggestedEditsEnableTopics' );
	/** @property {boolean} shouldUseTopicMatchMode Whether AND/OR topic toggle is supported */
	this.shouldUseTopicMatchMode = mw.config.get( 'wgGETopicsMatchModeEnabled' );
	/** @property {string} topicsMatchMode Topic match mode ('AND', 'OR') */
	this.topicsMatchMode = this.preferences.topicFilters ?
		this.preferences.topicFilters.getTopicsMatchMode() :
		TOPIC_MATCH_MODES.OR;
	/** @property {string[]} topics Array of selected topic IDs */
	this.topics = this.preferences.topicFilters ? this.preferences.topicFilters.getTopics() : [];
	/** @property {string[]} taskTypes Array of selected task type IDs */
	this.taskTypes = this.preferences.taskTypes;
	/** @property {Object} backup Backed up state, used when cancelling filter selection */
	this.backup = null;
}

OO.mixinClass( FiltersStore, OO.EventEmitter );

// Getters

/**
 * Get the IDs of the selected topics
 *
 * @return {string[]}
 */
FiltersStore.prototype.getSelectedTopics = function () {
	return this.topics;
};

/**
 * Get the IDs of the selected task types
 *
 * @return {string[]}
 */
FiltersStore.prototype.getSelectedTaskTypes = function () {
	return this.taskTypes;
};

/**
 * Get all topics organized by groups
 *
 * @return {Object}
 */
FiltersStore.prototype.getGroupedTopics = function () {
	return this.topicGroups;
};

/**
 * Get TopicFilters object for the current topic selection
 *
 * @return {mw.libs.ge.TopicFilters|null}
 */
FiltersStore.prototype.getTopicsQuery = function () {
	if ( this.topicsEnabled ) {
		const topicFiltersConfig = {
			topics: this.getSelectedTopics(),
		};
		if ( this.shouldUseTopicMatchMode ) {
			topicFiltersConfig.topicsMatchMode = this.topicsMatchMode || TOPIC_MATCH_MODES.OR;
		}
		return new TopicFilters( topicFiltersConfig );
	}
	return null;
};

/**
 * Get the selected task types
 *
 * @return {string[]}
 */
FiltersStore.prototype.getTaskTypesQuery = function () {
	return this.getSelectedTaskTypes();
};

// Mutations

/**
 * Replace the currently selected topics with the specified value if topic matching is supported
 *
 * @param {string[]} newTopics
 */
FiltersStore.prototype.setSelectedTopics = function ( newTopics ) {
	if ( !this.topicsEnabled ) {
		return;
	}
	this.topics = newTopics;
};

/**
 * Set the topic match mode if topic match mode is supported
 *
 * @param {string} topicsMatchMode
 */
FiltersStore.prototype.setTopicsMatchMode = function ( topicsMatchMode ) {
	if ( !this.shouldUseTopicMatchMode ) {
		return;
	}
	this.topicsMatchMode = topicsMatchMode;
};

/**
 * Set the selected topics and the topics match mode from TopicFilters object
 *
 * @param {mw.libs.ge.TopicFilters} topicFilters
 */
FiltersStore.prototype.updateStatesFromTopicsFilters = function ( topicFilters ) {
	this.setSelectedTopics( topicFilters.getTopics() );
	this.setTopicsMatchMode( topicFilters.getTopicsMatchMode() );
};

/**
 * Replace the currently selected task types with the specified value
 *
 * @param {string[]} newTaskTypes
 */
FiltersStore.prototype.setSelectedTaskTypes = function ( newTaskTypes ) {
	this.taskTypes = newTaskTypes;
};

// Events emitted when state changes for reactivity

/**
 * Emit an event when the filter selection changes
 */
FiltersStore.prototype.onSelectionChanged = function () {
	this.emit( CONSTANTS.EVENTS.FILTER_SELECTION_CHANGED );
};

// Actions

/**
 * Save the current filter selection to the user's preferences
 *
 * @return {jQuery.Promise}
 */
FiltersStore.prototype.savePreferences = function () {
	const updatedPreferences = {},
		topicPrefName = suggestedEditsConfig.GENewcomerTasksTopicFiltersPref,
		prefValueHasBeenSetBefore = mw.user.options.get( topicPrefName ),
		selectedTopics = this.getSelectedTopics(),
		topicFilters = this.getTopicsQuery();

	let topicPrefValue;
	if ( selectedTopics.length ) {
		topicPrefValue = JSON.stringify( selectedTopics );
	} else {
		topicPrefValue = prefValueHasBeenSetBefore ? JSON.stringify( [] ) : null;
	}
	updatedPreferences[ 'growthexperiments-homepage-se-filters' ] = JSON.stringify(
		this.getSelectedTaskTypes(),
	);
	if ( topicFilters ) {
		updatedPreferences[ 'growthexperiments-homepage-se-topic-filters-mode' ] = topicFilters.getTopicsMatchMode();
	}
	updatedPreferences[ topicPrefName ] = topicPrefValue;
	mw.user.options.set( updatedPreferences );
	this.onSelectionChanged();
	return new mw.Api().saveOptions( updatedPreferences ).then( () => {
		this.preferences = this.api.getPreferences();
	} );
};

/**
 * Back up the current state
 */
FiltersStore.prototype.backupState = function () {
	this.backup = {
		topics: this.topics,
		taskTypes: this.taskTypes,
		topicsMatchMode: this.topicsMatchMode,
	};
};

/**
 * Restore the backed up state
 */
FiltersStore.prototype.restoreState = function () {
	if ( !this.backup ) {
		throw new Error( 'No state backed up' );
	}
	this.topics = this.backup.topics;
	this.taskTypes = this.backup.taskTypes;
	this.topicsMatchMode = this.backup.topicsMatchMode;
	this.backup = null;
};

// Helpers

/**
 * Return topics organized by groups
 *
 * @param {Object} topicData Topic data from Topics.json virtual file
 * @return {Object|null}
 */
FiltersStore.prototype.formatTopicGroups = function ( topicData ) {
	/* eslint-disable no-underscore-dangle */
	if ( topicData._error ) {
		mw.log.error( 'Unable to load topic data for suggested edits: ' + topicData._error );
		mw.errorLogger.logError( new Error( 'Unable to load topic data for suggested edits: ' +
			topicData._error ), 'error.growthexperiments' );
		return null;
	}
	/* eslint-enable no-underscore-dangle */

	const grouped = {};
	for ( const key in topicData ) {
		const topic = topicData[ key ];
		if ( grouped[ topic.groupId ] === undefined ) {
			grouped[ topic.groupId ] = {
				id: topic.groupId,
				name: topic.groupName,
				topics: [],
			};
		}
		grouped[ topic.groupId ].topics.push( topic );
	}
	return grouped;
};

module.exports = FiltersStore;
