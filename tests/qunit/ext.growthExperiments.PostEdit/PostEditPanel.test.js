'use strict';

const PostEditPanel = require( 'ext.growthExperiments.PostEdit/PostEditPanel.js' );
const NewcomerTaskLogger = require( 'ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.js' );
const HelpPanelLogger = require( '../../../modules/utils/HelpPanelLogger.js' );
const NewcomerTasksStore = require( '../../../modules/ext.growthExperiments.DataStore/NewcomerTasksStore.js' );

QUnit.module( 'ext.growthExperiments.PostEdit/PostEditPanel.js', QUnit.newMwEnvironment() );

QUnit.test( 'should generate a task URL with task token and log an impression when calling getCard', ( assert ) => {
	const task = {
		title: 'Some title',
		token: '1234',
		tasktype: 'copyedit',
		pageId: 73,
	};

	const newcomerTaskLogger = new NewcomerTaskLogger();
	sinon.spy( newcomerTaskLogger, 'log' );
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskTypes: {
			copyedit: {
				messages: {
					name: 'some-text-key',
				},
			},
		},
		helpPanelLogger: {
			helpPanelSessionId: 'session-id-1234',
		},
		newcomerTaskLogger: newcomerTaskLogger,
	} );

	const card = panel.getCard( task );

	const expectedParams = {
		geclickid: 'session-id-1234',
		getasktype: 'copyedit',
		genewcomertasktoken: '1234',
		gesuggestededit: 1,
	};
	const expectedHref = new mw.Title( 'Special:Homepage/newcomertask/' + task.pageId )
		.getUrl( expectedParams );

	assert.strictEqual( card.attr( 'href' ), expectedHref );
	assert.strictEqual( newcomerTaskLogger.log.calledOnce, true );
	assert.strictEqual( newcomerTaskLogger.log.calledWith( task ), true );

} );

QUnit.test( 'should log an impression when calling logImpression', ( assert ) => {
	const task = {
		title: 'Some title',
		token: '1234',
		tasktype: 'copyedit',
		pageId: 73,

	};
	const helpPanelLogger = new HelpPanelLogger();
	sinon.spy( helpPanelLogger, 'log' );

	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		nextTask: task,
		taskTypes: {
			copyedit: {
				messages: {
					name: 'some-text-key',
				},
			},
		},
		helpPanelLogger: helpPanelLogger,
	} );

	const extraData = {
		savedTaskType: 'copyedit',
		userTaskTypes: 'extraData.userTaskTypes',
		newcomerTaskToken: '4321',
	};
	panel.logImpression( extraData );

	const expectedArgs = [
		'postedit-impression',
		{
			type: 'full',
			savedTaskType: 'copyedit',
			userTaskTypes: 'extraData.userTaskTypes',
			newcomerTaskToken: '4321',
		},
	];

	assert.strictEqual( helpPanelLogger.log.calledOnce, true );
	assert.deepEqual( helpPanelLogger.log.firstCall.args, expectedArgs );

} );

QUnit.test( 'should log postedit-task-navigation when calling onPrevButtonClicked and onNextButtonClicked', ( assert ) => {
	const helpPanelLogger = new HelpPanelLogger();
	const newcomerTaskLogger = new NewcomerTaskLogger();
	sinon.spy( helpPanelLogger, 'log' );
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		helpPanelLogger,
		newcomerTaskLogger,
	} );
	const getExpectedArgs = function ( dir ) {
		return [
			'postedit-task-navigation',
			{
				dir,
				/* eslint-disable-next-line camelcase */
				navigation_type: 'click',
			},
		];
	};
	panel.onPrevButtonClicked();
	panel.onNextButtonClicked();
	assert.deepEqual( helpPanelLogger.log.firstCall.args, getExpectedArgs( 'prev' ) );
	assert.deepEqual( helpPanelLogger.log.lastCall.args, getExpectedArgs( 'next' ) );
} );

QUnit.test( 'should return success toast message when edits have been published when wgEditSubmitButtonLabelPublish=false', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'saved',
		taskType: 'copyedit',
	} );
	this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgEditSubmitButtonLabelPublish' ).returns( false );
	const spy = this.sandbox.spy( mw, 'message' );
	const postEditToastMessage = panel.getPostEditToastMessage();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-success-message-saved' ) );
	assert.strictEqual( postEditToastMessage.type, 'success' );
} );

QUnit.test( 'should return success toast message when edits have been published when wgEditSubmitButtonLabelPublish=true', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'saved',
		taskType: 'copyedit',
	} );
	this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgEditSubmitButtonLabelPublish' ).returns( true );
	const spy = this.sandbox.spy( mw, 'message' );
	const postEditToastMessage = panel.getPostEditToastMessage();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-success-message-published' ) );
	assert.strictEqual( postEditToastMessage.type, 'success' );
} );

QUnit.test( 'should return success toast message when edits have been published and image recommendation daily limit is reached', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'saved',
		taskType: 'image-recommendation',
		imageRecommendationDailyTasksExceeded: true,
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	const postEditToastMessage = panel.getPostEditToastMessage();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-image-recommendation' ) );
	assert.strictEqual( postEditToastMessage.type, 'success' );
} );

QUnit.test( 'should return success toast message when edits have been published and link recommendation daily limit is reached', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'saved',
		taskType: 'link-recommendation',
		linkRecommendationDailyTasksExceeded: true,
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	const postEditToastMessage = panel.getPostEditToastMessage();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-link-recommendation' ) );
	assert.strictEqual( postEditToastMessage.type, 'success' );
} );

QUnit.test( 'should return notice toast message when edits have not been published', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'submitted',
		taskType: 'link-recommendation',
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	const postEditToastMessage = panel.getPostEditToastMessage();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-success-message-notsaved' ) );
	assert.strictEqual( postEditToastMessage.type, 'notice' );
} );

QUnit.test( 'should return notice toast message when edits have not been published and link recommendation daily limit is reached', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'submitted',
		taskType: 'link-recommendation',
		linkRecommendationDailyTasksExceeded: true,
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	const postEditToastMessage = panel.getPostEditToastMessage();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-link-recommendation' ) );
	assert.strictEqual( postEditToastMessage.type, 'notice' );
} );

QUnit.test( 'should return alternate header text when image recommendation daily limit is reached', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'saved',
		taskType: 'image-recommendation',
		imageRecommendationDailyTasksExceeded: true,
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	panel.getHeaderText();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-subheader-image-recommendation' ) );
} );

QUnit.test( 'should return alternate header text when link recommendation daily limit is reached', function ( assert ) {
	const panel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'saved',
		taskType: 'link-recommendation',
		linkRecommendationDailyTasksExceeded: true,
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	panel.getHeaderText();
	assert.true( spy.calledWith( 'growthexperiments-help-panel-postedit-subheader-link-recommendation' ) );
} );

QUnit.test( 'should return generic header text for image recommendation if the daily limit has not been reached', ( assert ) => {
	const acceptedSuggestionPanel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'saved',
		taskType: 'image-recommendation',
	} );
	const reviewedSuggestionPanel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskState: 'submitted',
		taskType: 'image-recommendation',
	} );
	assert.strictEqual(
		acceptedSuggestionPanel.getHeaderText(),
		'(growthexperiments-help-panel-postedit-subheader)',
	);
	assert.strictEqual(
		reviewedSuggestionPanel.getHeaderText(),
		'(growthexperiments-help-panel-postedit-subheader-notsaved)',
	);
} );
