'use strict';

const PostEditPanel = require( '../../../modules/ext.growthExperiments.PostEdit/PostEditPanel.js' );
const NewcomerTaskLogger = require( '../../../modules/ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' );
const HelpPanelLogger = require( '../../../modules/ext.growthExperiments.Help/HelpPanelLogger.js' );

QUnit.module( 'ext.growthExperiments.PostEdit/PostEditPanel.js', QUnit.newMwEnvironment() );

QUnit.test( 'should generate a task URL with task token and log an impression when calling getCard', function ( assert ) {
	const task = {
		title: 'Some title',
		token: '1234',
		tasktype: 'copyedit',
		pageId: 73
	};

	const newcomerTaskLogger = new NewcomerTaskLogger();
	sinon.spy( newcomerTaskLogger, 'log' );
	const panel = new PostEditPanel( {
		taskTypes: {
			copyedit: {
				messages: {
					name: 'some-text-key'
				}
			}
		},
		helpPanelLogger: {
			helpPanelSessionId: 'session-id-1234'
		},
		newcomerTaskLogger: newcomerTaskLogger
	} );

	const card = panel.getCard( task );

	const expectedParams = {
		geclickid: 'session-id-1234',
		getasktype: 'copyedit',
		genewcomertasktoken: '1234',
		gesuggestededit: 1
	};
	const expectedHref = new mw.Title( 'Special:Homepage/newcomertask/' + task.pageId )
		.getUrl( expectedParams );

	assert.strictEqual( card.attr( 'href' ), expectedHref );
	assert.strictEqual( newcomerTaskLogger.log.calledOnce, true );
	assert.strictEqual( newcomerTaskLogger.log.calledWith( task ), true );

} );

QUnit.test( 'should log an impression when calling logImpression', function ( assert ) {
	const task = {
		title: 'Some title',
		token: '1234',
		tasktype: 'copyedit',
		pageId: 73

	};
	const helpPanelLogger = new HelpPanelLogger();
	sinon.spy( helpPanelLogger, 'log' );

	const panel = new PostEditPanel( {
		nextTask: task,
		taskTypes: {
			copyedit: {
				messages: {
					name: 'some-text-key'
				}
			}
		},
		helpPanelLogger: helpPanelLogger
	} );

	const extraData = {
		savedTaskType: 'copyedit',
		userTaskTypes: 'extraData.userTaskTypes',
		newcomerTaskToken: '4321'
	};
	panel.logImpression( extraData );

	const expectedArgs = [
		'postedit-impression',
		{
			type: 'full',
			savedTaskType: 'copyedit',
			userTaskTypes: 'extraData.userTaskTypes',
			newcomerTaskToken: '4321'
		}
	];

	assert.strictEqual( helpPanelLogger.log.calledOnce, true );
	assert.deepEqual( helpPanelLogger.log.firstCall.args, expectedArgs );

} );
