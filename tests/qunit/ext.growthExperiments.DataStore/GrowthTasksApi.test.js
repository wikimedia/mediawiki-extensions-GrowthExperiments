'use strict';
const GrowthTasksApi = require( '../../../modules/ext.growthExperiments.DataStore/GrowthTasksApi.js' );
const { TOPIC_MATCH_MODES } = require( '../../../modules/ext.growthExperiments.DataStore/constants.js' );
const TopicFilters = require( '../../../modules/ext.growthExperiments.DataStore/TopicFilters.js' );

QUnit.module( 'ext.growthExperiments.DataStore/GrowthTasksApi.js', QUnit.newMwEnvironment( {} ) );

QUnit.test( 'should fetch tasks', function ( assert ) {
	const done = assert.async();
	const api = new GrowthTasksApi( {
		taskTypes: {
			copyedit: {
				id: 'copyedit'
			}
		},
		suggestedEditsConfig: {
			GENewcomerTasksTopicFiltersPref: 'preference-name',
			GESearchTaskSuggesterDefaultLimit: 20
		}
	} );
	const topicFilters = new TopicFilters( {
		topics: [ 'art', 'music' ],
		topicsMatchMode: TOPIC_MATCH_MODES.AND
	} );

	const responseMock = {
		batchcomplete: true,
		query: {
			pages: Array( 23 ).fill( 1 ).map( ( _, i ) => ( { pageid: i + 1 } ) )
		},
		growthtasks: {
			totalCount: 24
		}
	};
	this.sandbox.stub( mw.Api.prototype, 'get' ).returns(
		$.Deferred().resolve( responseMock ).promise( {
			abort: function () {}
		} )
	);
	const expectedParams = {
		action: 'query',
		formatversion: 2,
		generator: 'growthtasks',
		ggtlimit: 25,
		ggttasktypes: [ 'copyedit' ],
		piprop: 'name|original|thumbnail',
		pithumbsize: 332,
		prop: 'info|revisions|pageimages',
		rvprop: 'ids',
		uselang: 'qqx',
		ggttopics: [ 'art', 'music' ],
		ggttopicsmode: 'AND'
	};
	api.fetchTasks( [ 'copyedit' ], topicFilters ).then( ( response ) => {
		assert.strictEqual( mw.Api.prototype.get.calledOnce, true );
		assert.deepEqual( mw.Api.prototype.get.getCall( 0 ).args[ 0 ], expectedParams );
		// TODO add assertions for response post processing instead of length
		assert.strictEqual( response.tasks.length, 20 );
		assert.strictEqual( response.count, 24 );
		assert.strictEqual( response.hasNext, true );
		done();
	} );
} );

QUnit.test( 'should send topic match mode even if topics are empty and use configured lookahead size',
	function ( assert ) {
		const done = assert.async();
		const api = new GrowthTasksApi( {
			taskTypes: {
				copyedit: {
					id: 'copyedit'
				}
			},
			suggestedEditsConfig: {
				GENewcomerTasksTopicFiltersPref: 'preference-name',
				GESearchTaskSuggesterDefaultLimit: 20,
				GEApiQueryGrowthTasksLookaheadSize: 10
			}
		} );
		const topicFilters = new TopicFilters( {
			topics: [],
			topicsMatchMode: TOPIC_MATCH_MODES.AND
		} );

		const response = {
			batchcomplete: true,
			query: {
				pages: []
			},
			growthtasks: {
				totalCount: 3
			}
		};
		this.sandbox.stub( mw.Api.prototype, 'get' ).returns(
			$.Deferred().resolve( response ).promise( {
				abort: function () {}
			} )
		);
		const expectedParams = {
			action: 'query',
			formatversion: 2,
			generator: 'growthtasks',
			ggtlimit: 30,
			ggttasktypes: [ 'copyedit' ],
			piprop: 'name|original|thumbnail',
			pithumbsize: 332,
			prop: 'info|revisions|pageimages',
			rvprop: 'ids',
			uselang: 'qqx',
			ggttopicsmode: 'AND'
		};
		api.fetchTasks( [ 'copyedit' ], topicFilters ).then( () => {
			assert.strictEqual( mw.Api.prototype.get.calledOnce, true );
			assert.deepEqual( mw.Api.prototype.get.getCall( 0 ).args[ 0 ], expectedParams );
			// TODO add assertions for response post processing
			done();
		} );
	} );

QUnit.test( 'should read topic filters and topics match mode preferences', function ( assert ) {
	const api = new GrowthTasksApi( {
		taskTypes: {
			copyedit: {
				id: 'copyedit'
			}
		},
		suggestedEditsConfig: {
			GENewcomerTasksTopicFiltersPref: 'preference-name'
		}
	} );
	const getOptionsStub = this.sandbox.stub( mw.user.options, 'get' );
	getOptionsStub.withArgs( 'growthexperiments-homepage-se-filters' ).returns( '["copyedit"]' );
	getOptionsStub.withArgs( 'preference-name' ).returns( '["art", "music"]' );
	getOptionsStub.withArgs( 'growthexperiments-homepage-se-topic-filters-mode' ).returns( null );

	const expectedPreferences1 = {
		taskTypes: [ 'copyedit' ],
		topicFilters: new TopicFilters( {
			topics: [ 'art', 'music' ],
			topicsMatchMode: null
		} )
	};
	const actual1 = api.getPreferences();
	assert.deepEqual( actual1, expectedPreferences1 );

	getOptionsStub.withArgs( 'growthexperiments-homepage-se-topic-filters-mode' ).returns( 'AND' );
	const expectedPreferences2 = {
		taskTypes: [ 'copyedit' ],
		topicFilters: new TopicFilters( {
			topics: [ 'art', 'music' ],
			topicsMatchMode: TOPIC_MATCH_MODES.AND
		} )
	};
	const actual2 = api.getPreferences();
	assert.deepEqual( actual2, expectedPreferences2 );
	// GENewcomerTasksTopicFiltersPref null indicates the user never saved
	// the preference
	getOptionsStub.withArgs( 'preference-name' ).returns( null );
	const expectedPreferences3 = {
		taskTypes: [ 'copyedit' ],
		topicFilters: null
	};
	const actual3 = api.getPreferences();
	assert.deepEqual( actual3, expectedPreferences3 );
} );

QUnit.test( 'should handle undefined values in config correctly', function ( assert ) {
	const done = assert.async();
	const api = new GrowthTasksApi( {
		taskTypes: {
			copyedit: { id: 'copyedit' }
		},
		suggestedEditsConfig: {
			GENewcomerTasksTopicFiltersPref: 'preference-name',
			GESearchTaskSuggesterDefaultLimit: 20
		}
	} );

	const topicFilters = new TopicFilters( {
		topics: [ 'art', 'music' ],
		topicsMatchMode: TOPIC_MATCH_MODES.AND
	} );

	const responseMock = {
		batchcomplete: true,
		query: { pages: [] },
		growthtasks: { totalCount: 0 }
	};

	this.sandbox.stub( mw.Api.prototype, 'get' ).returns(
		$.Deferred().resolve( responseMock ).promise( { abort: function () {} } )
	);

	// Config with undefined value
	const testConfig = {
		getDescription: undefined,
		size: 10,
		thumbnailWidth: undefined
	};

	api.fetchTasks( [ 'copyedit' ], topicFilters, testConfig ).then( () => {
		const apiCallArgs = mw.Api.prototype.get.getCall( 0 ).args[ 0 ];

		assert.strictEqual(
			apiCallArgs.ggtlimit, 15, 'size should be 10 (from config) + 5 (lookAheadSize)' );
		assert.strictEqual( apiCallArgs.prop.indexOf( 'description' ), -1,
			'description should not be in prop (getDescription should remain false)' );
		assert.strictEqual( apiCallArgs.pithumbsize, 332,
			'thumbnailWidth should retain default value when undefined is provided' );

		done();
	} ).catch( ( error ) => {
		// eslint-disable-next-line qunit/no-loose-assertions
		assert.ok( false, 'Promise should not be rejected: ' + error );
		done();
	} );
} );
