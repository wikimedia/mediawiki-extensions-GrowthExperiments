'use strict';

const FiltersStore = require( '../../../modules/ext.growthExperiments.DataStore/FiltersStore.js' );
const TopicFilters = require( '../../../modules/ext.growthExperiments.DataStore/TopicFilters.js' );
const topicData = require( '../__mocks__/Topics.json' );
const groupedTopics = require( '../__mocks__/GroupedTopics.json' );

const setupOptionsStub = ( sandbox, { taskTypes, topics, topicsMatchMode } ) => {
	const getOptionsStub = sandbox.stub( mw.user.options, 'get' );
	getOptionsStub.withArgs( 'growthexperiments-homepage-se-filters' ).returns(
		JSON.stringify( taskTypes )
	);
	getOptionsStub.withArgs( 'growthexperiments-homepage-se-ores-topic-filters' ).returns(
		JSON.stringify( topics )
	);
	if ( topicsMatchMode ) {
		getOptionsStub.withArgs( 'growthexperiments-homepage-se-topic-filters-mode' ).returns( topicsMatchMode );
	}
};

const setupConfigStub = ( sandbox, { topicsEnabled, shouldUseTopicMatchMode } ) => {
	const getConfigStub = sandbox.stub( mw.config, 'get' );
	getConfigStub.withArgs( 'GEHomepageSuggestedEditsEnableTopics' ).returns( topicsEnabled );
	getConfigStub.withArgs( 'wgGETopicsMatchModeEnabled' ).returns( shouldUseTopicMatchMode );
};

QUnit.module( 'ext.growthExperiments.DataStore/FiltersStore.js', QUnit.newMwEnvironment( {} ) );

QUnit.test( 'should set initial states based on configuration values and user preferences', function ( assert ) {
	const topicsEnabled = true;
	const shouldUseTopicMatchMode = true;
	const selectedTaskTypes = [ 'copyedit' ];
	const selectedTopics = [ 'architecture' ];

	setupOptionsStub( this.sandbox, {
		taskTypes: selectedTaskTypes,
		topics: selectedTopics,
		topicsMatchMode: 'OR'
	} );
	setupConfigStub( this.sandbox, { topicsEnabled, shouldUseTopicMatchMode } );

	const filtersStore = new FiltersStore();
	assert.strictEqual( filtersStore.topicsEnabled, topicsEnabled );
	assert.strictEqual( filtersStore.shouldUseTopicMatchMode, shouldUseTopicMatchMode );
	assert.deepEqual( filtersStore.getSelectedTopics(), selectedTopics );
	assert.deepEqual( filtersStore.getSelectedTaskTypes(), selectedTaskTypes );
} );

QUnit.test( 'should return TopicFilters for getTopicsQuery if topics are enabled', function ( assert ) {
	const selectedTaskTypes = [ 'copyedit' ];
	const selectedTopics = [ 'architecture' ];
	const topicsMatchMode = 'AND';
	setupOptionsStub( this.sandbox, {
		taskTypes: selectedTaskTypes,
		topics: selectedTopics,
		topicsMatchMode
	} );
	setupConfigStub( this.sandbox, {
		topicsEnabled: false,
		shouldUseTopicMatchMode: true
	} );

	const filtersStore = new FiltersStore();
	assert.strictEqual( filtersStore.getTopicsQuery(), null );

	filtersStore.topicsEnabled = true;
	assert.true( filtersStore.getTopicsQuery() instanceof TopicFilters );
	assert.deepEqual( filtersStore.getTopicsQuery().getTopics(), selectedTopics );
	assert.deepEqual( filtersStore.getTopicsQuery().getTopicsMatchMode(), topicsMatchMode );
} );

QUnit.test( 'should return an array of selected task types for getTaskTypesQuery', function ( assert ) {
	const selectedTaskTypes = [ 'copyedit', 'references' ];
	setupOptionsStub( this.sandbox, {
		taskTypes: selectedTaskTypes,
		topics: []
	} );
	const filtersStore = new FiltersStore();
	assert.deepEqual( filtersStore.getTaskTypesQuery(), selectedTaskTypes );
} );

QUnit.test( 'should return topics organized by groups', function ( assert ) {
	setupOptionsStub( this.sandbox, {
		taskTypes: [ 'copyedit' ],
		topics: []
	} );
	const filtersStore = new FiltersStore();
	assert.deepEqual( filtersStore.formatTopicGroups( topicData ), groupedTopics );
	assert.deepEqual( filtersStore.getGroupedTopics(), groupedTopics );
} );

QUnit.test( 'should update the selected topics and topics match mode for updateStatesFromTopicsFilters', function ( assert ) {
	const initialTopicsMatchMode = 'OR';
	const initialTopics = [];
	setupOptionsStub( this.sandbox, {
		taskTypes: [ 'copyedit' ],
		topics: initialTopics,
		topicsMatchMode: initialTopicsMatchMode
	} );
	const filtersStore = new FiltersStore();
	const topics = [ 'art', 'architecture' ];
	const topicsMatchMode = 'AND';
	const topicFilters = new TopicFilters( { topics, topicsMatchMode } );
	filtersStore.topicsEnabled = false;
	filtersStore.updateStatesFromTopicsFilters( topicFilters );
	assert.deepEqual( filtersStore.getSelectedTopics(), initialTopics );
	assert.deepEqual( filtersStore.topicsMatchMode, initialTopicsMatchMode );

	filtersStore.topicsEnabled = true;
	filtersStore.shouldUseTopicMatchMode = true;
	filtersStore.updateStatesFromTopicsFilters( topicFilters );
	assert.deepEqual( filtersStore.getSelectedTopics(), topics );
	assert.deepEqual( filtersStore.topicsMatchMode, topicsMatchMode );
} );

QUnit.test( 'should save the selected filters to preferences and set the mw.user.options object with savePreferences', function ( assert ) {
	const saveOptionsStub = this.sandbox.stub( mw.Api.prototype, 'saveOptions' ).returns(
		$.Deferred().resolve( {} ).promise()
	);
	const setOptionsSpy = this.sandbox.spy( mw.user.options, 'set' );
	const selectedTaskTypes = [ 'copyedit' ];
	const selectedTopics = [ 'architecture' ];
	setupOptionsStub( this.sandbox, {
		taskTypes: selectedTaskTypes,
		topics: selectedTopics
	} );

	const filtersStore = new FiltersStore();
	filtersStore.savePreferences();
	const updatedPreferences = {};
	updatedPreferences[ 'growthexperiments-homepage-se-filters' ] = JSON.stringify( selectedTaskTypes );
	updatedPreferences[ 'growthexperiments-homepage-se-ores-topic-filters' ] = JSON.stringify( selectedTopics );
	assert.deepEqual( saveOptionsStub.firstCall.args[ 0 ], updatedPreferences );
	assert.deepEqual( setOptionsSpy.firstCall.args[ 0 ], updatedPreferences );
} );

QUnit.test( 'should store the selected filters in backup with backupState', function ( assert ) {
	const taskTypes = [ 'copyedit' ];
	const topics = [ 'architecture' ];
	const topicsMatchMode = 'AND';
	setupOptionsStub( this.sandbox, {
		taskTypes,
		topics,
		topicsMatchMode
	} );

	const filtersStore = new FiltersStore();
	assert.strictEqual( filtersStore.backup, null );
	filtersStore.backupState();
	assert.deepEqual( filtersStore.backup, {
		topics,
		taskTypes,
		topicsMatchMode
	} );
} );

QUnit.test( 'should set the selected filters to backed up state with restoreState', function ( assert ) {
	const taskTypes = [ 'copyedit' ];
	const topics = [ 'architecture' ];
	const topicsMatchMode = 'AND';
	setupOptionsStub( this.sandbox, {
		taskTypes,
		topics,
		topicsMatchMode
	} );

	const filtersStore = new FiltersStore();
	filtersStore.backupState();
	filtersStore.setSelectedTopics( [ 'art' ] );
	filtersStore.setSelectedTaskTypes( [] );
	filtersStore.topicsMatchMode = 'OR';

	filtersStore.restoreState();
	assert.deepEqual( filtersStore.getSelectedTaskTypes(), taskTypes );
	assert.deepEqual( filtersStore.getSelectedTopics(), topics );
	assert.strictEqual( filtersStore.topicsMatchMode, topicsMatchMode );
	assert.strictEqual( filtersStore.backup, null );
} );
