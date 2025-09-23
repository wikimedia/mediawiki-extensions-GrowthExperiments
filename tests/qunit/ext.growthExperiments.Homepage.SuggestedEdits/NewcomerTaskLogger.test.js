'use strict';

const NewcomerTaskLogger = require( 'ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' );

QUnit.module( 'ext.growthExperiments.NewcomerTaskLogger.js', QUnit.newMwEnvironment() );

QUnit.test( 'constructor', ( assert ) => {
	const logger = new NewcomerTaskLogger();
	assert.true( logger instanceof NewcomerTaskLogger );
} );

QUnit.test( 'should log impressions', function ( assert ) {
	const logger = new NewcomerTaskLogger();
	this.sandbox.spy( mw, 'track' );
	const task = {
		pageId: 101,
		revisionId: 102,
		tasktype: 'copyedit',
		title: 'Some title',
		token: '1234',
	};
	logger.log( task, 100 );

	assert.strictEqual( mw.track.calledOnce, true );
	assert.deepEqual( mw.track.firstCall.args, [
		'event.NewcomerTask',
		/* eslint-disable camelcase */
		{
			has_image: false,
			maintenance_templates: [],
			newcomer_task_token: '1234',
			ordinal_position: 100,
			page_id: 101,
			page_title: 'Some title',
			revision_id: 102,
			task_type: 'copyedit',
			/* eslint-enable camelcase */
		},
	] );
} );

QUnit.test( 'should get log metadata', ( assert ) => {
	const logger = new NewcomerTaskLogger();

	const task = {
		pageId: 101,
		revisionId: 102,
		tasktype: 'copyedit',
		title: 'Some title',
		token: '1234',
		pageviews: null,
	};
	const data = logger.getLogData( task, 100 );

	assert.deepEqual( data, {
		/* eslint-disable camelcase */
		has_image: false,
		maintenance_templates: [],
		newcomer_task_token: '1234',
		ordinal_position: 100,
		page_id: 101,
		page_title: 'Some title',
		revision_id: 102,
		task_type: 'copyedit',
		/* eslint-enable camelcase */
	} );
} );
