'use strict';
const StructuredTaskLogger = require( '../../../modules/ext.growthExperiments.StructuredTask/StructuredTaskLogger.js' );

let mwEventLog;

QUnit.module( 'ext.growthExperiments.StructuredTask/StructuredTaskLogger.js', QUnit.newMwEnvironment( {
	beforeEach: function () {
		mwEventLog = mw.eventLog;
		mw.eventLog = { submit: function () {} };
	},
	afterEach: function () {
		mw.eventLog = mwEventLog;
	},
} ) );

QUnit.test( 'should log events', function ( assert ) {
	this.sandbox.stub( mw.eventLog, 'submit' );
	mw.config.set( 'wgArticleId', 0 );

	const logger = new StructuredTaskLogger( 'analytics/schema', 'some.stream', {} );
	logger.log( 'action_name', 'data', {
		/* eslint-disable camelcase */
		page_title: 'overriden_page',
		/* eslint-enable camelcase */
	} );
	assert.strictEqual( mw.eventLog.submit.calledOnce, true );
	assert.strictEqual( mw.eventLog.submit.firstCall.args[ 0 ], 'some.stream' );
	assert.deepEqual( mw.eventLog.submit.firstCall.args[ 1 ], {
		/* eslint-disable camelcase */
		$schema: 'analytics/schema',
		action: 'action_name',
		action_data: 'data',
		homepage_pageview_token: null,
		newcomer_task_token: null,
		page_id: 0,
		page_title: 'overriden_page',
		/* eslint-enable camelcase */
	} );
} );
