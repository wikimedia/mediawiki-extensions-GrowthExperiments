'use strict';

const NewcomerTasksStore = require( '../../../modules/ext.growthExperiments.DataStore/NewcomerTasksStore.js' );
const EVENT_TASK_QUEUE_CHANGED = 'taskQueueChanged';
const store = require( '../__mocks__/store.js' );

/**
 * Get mock task data
 *
 * @param {string} title
 * @param {string} tasktype
 *
 * @return {mw.libs.ge.TaskData}
 */
const getTaskData = ( title, tasktype ) => {
	return {
		title,
		tasktype,
		difficulty: 'easy',
		qualityGateIds: [],
		qualityGateConfig: {},
		url: null,
		token: 'token-' + title
	};
};

const stubApiRequests = ( sandbox, tasksStore ) => {
	sandbox.stub( tasksStore, 'fetchMoreTasks' ).returns( $.Deferred().resolve() );
	sandbox.stub( tasksStore, 'preloadExtraDataForUpcomingTask' ).returns( $.Deferred().resolve() );
};

QUnit.module( 'ext.growthExperiments.DataStore/NewcomerTasksStore.js', QUnit.newMwEnvironment( {
	beforeEach() {
		const getOptionsStub = this.sandbox.stub( mw.user.options, 'get' );
		getOptionsStub.withArgs( 'growthexperiments-homepage-se-filters' ).returns(
			JSON.stringify( [ 'copyedit' ] )
		);
		getOptionsStub.withArgs( 'growthexperiments-homepage-se-topic-filters' ).returns(
			JSON.stringify( [ 'architecture' ] )
		);
	}
} ) );

QUnit.test( 'should set initial states based on configuration values and user preferences', function ( assert ) {
	const taskCount = 10;
	const getConfigStub = this.sandbox.stub( mw.config, 'get' );
	getConfigStub.withArgs( 'wgGEHomepageModuleActionData-suggested-edits' ).returns( { taskCount } );

	const tasksStore = new NewcomerTasksStore( store );
	assert.false( tasksStore.taskQueueLoading );
	assert.deepEqual( tasksStore.getTaskQueue(), [] );
	assert.strictEqual( tasksStore.getTaskCount(), taskCount );
	assert.strictEqual( tasksStore.currentTaskIndex, 0 );
	assert.deepEqual( tasksStore.qualityGateConfig, {} );
} );

QUnit.test( 'should return states about the task queue', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	stubApiRequests( this.sandbox, tasksStore );
	const taskQueue = ( [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'references' ),
		getTaskData( '3', 'copyedit' )
	] );
	tasksStore.setTaskQueue( taskQueue );

	assert.strictEqual( tasksStore.getTaskCount(), 3 );
	assert.strictEqual( tasksStore.getCurrentTask(), taskQueue[ 0 ] );
	assert.false( tasksStore.isTaskQueueEmpty() );
	assert.strictEqual( tasksStore.getQueuePosition(), 0 );
	assert.false( tasksStore.hasPreviousTask() );
	assert.true( tasksStore.hasNextTask() );
	assert.false( tasksStore.isEndOfTaskQueue() );

	tasksStore.showNextTask();
	assert.strictEqual( tasksStore.getQueuePosition(), 1 );
	assert.true( tasksStore.hasPreviousTask() );
	assert.true( tasksStore.hasNextTask() );
	assert.false( tasksStore.isEndOfTaskQueue() );

	tasksStore.showNextTask();
	assert.strictEqual( tasksStore.getQueuePosition(), 2 );
	assert.true( tasksStore.hasPreviousTask() );
	assert.false( tasksStore.hasNextTask() );
	assert.true( tasksStore.isEndOfTaskQueue() );

	tasksStore.showPreviousTask();
	tasksStore.showPreviousTask();
	assert.strictEqual( tasksStore.getQueuePosition(), 0 );
	assert.false( tasksStore.hasPreviousTask() );
	assert.true( tasksStore.hasNextTask() );
	assert.false( tasksStore.isEndOfTaskQueue() );
} );

QUnit.test( 'should emit taskQueueChanged event with showPreviousTask', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	stubApiRequests( this.sandbox, tasksStore );
	const spy = this.sandbox.spy( tasksStore, 'emit' );
	tasksStore.setTaskQueue( [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'references' )
	] );
	tasksStore.currentTaskIndex = 1;
	tasksStore.showPreviousTask();
	assert.true( spy.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
} );

QUnit.test( 'should emit taskQueueChanged event with showNextTask', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	stubApiRequests( this.sandbox, tasksStore );
	const spy = this.sandbox.spy( tasksStore, 'emit' );
	tasksStore.setTaskQueue( [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'references' )
	] );
	tasksStore.showNextTask();
	assert.true( spy.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
} );

QUnit.test( 'should fetch more tasks when the end of the task queue is reached', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	const onFetchedMoreTasksSpy = this.sandbox.spy( tasksStore, 'onFetchedMoreTasks' );
	const fetchMoreTasksStub = this.sandbox.stub( tasksStore, 'fetchMoreTasks' );
	fetchMoreTasksStub.returns( $.Deferred().resolve() );
	tasksStore.setTaskQueue( [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'references' )
	] );
	tasksStore.showNextTask();
	assert.true( onFetchedMoreTasksSpy.firstCall.calledWithExactly( true ) );
	assert.true( fetchMoreTasksStub.calledOnce );
} );

QUnit.test( 'should emit an event when the task queue is replaced', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	const spy = this.sandbox.spy( tasksStore, 'emit' );
	tasksStore.setTaskQueue( [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'references' )
	] );
	assert.strictEqual( spy.firstCall.args[ 0 ], EVENT_TASK_QUEUE_CHANGED );
} );

QUnit.test( 'should preload extra data for the next task in the queue when showing the next task', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	this.sandbox.stub( tasksStore, 'fetchMoreTasks' ).returns( $.Deferred().resolve() );
	const stub = this.sandbox.stub( tasksStore, 'fetchExtraDataForTaskIndex' );
	stub.returns( $.Deferred().resolve() );
	tasksStore.setTaskQueue( [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'references' ),
		getTaskData( '3', 'references' )
	] );
	tasksStore.showNextTask();
	assert.strictEqual( stub.callCount, 1 );
	assert.strictEqual( stub.firstCall.args[ 0 ], 2 );
	tasksStore.showNextTask();
	// end of task queue reached so fetchExtraDataForTaskIndex should not be called
	assert.strictEqual( stub.callCount, 1 );
} );

QUnit.test( 'should not emit an event when additional tasks are added to the task queue', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	const spy = this.sandbox.spy( tasksStore, 'emit' );
	tasksStore.addToTaskQueue( [ getTaskData( '1', 'copyedit' ) ] );
	assert.true( spy.notCalled );
} );

QUnit.test( 'should set the preloaded task in the task queue', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	const preloadedTask = getTaskData( '1', 'copyedit' );
	const qualityGateConfig = { dailyLimit: 10 };
	preloadedTask.qualityGateConfig = qualityGateConfig;

	assert.deepEqual( tasksStore.getTaskQueue(), [] );
	assert.deepEqual( tasksStore.getQualityGateConfig(), {} );
	assert.true( tasksStore.getCurrentTask() === undefined );
	tasksStore.setPreloadedFirstTask( preloadedTask );
	assert.deepEqual( tasksStore.getTaskQueue(), [ preloadedTask ] );
	assert.deepEqual( tasksStore.getCurrentTask(), preloadedTask );
	assert.deepEqual( tasksStore.getQualityGateConfig(), qualityGateConfig );
} );

QUnit.test( 'should store the current states in backup with backupState', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	stubApiRequests( this.sandbox, tasksStore );
	const taskQueue = [ getTaskData( '1', 'copyedit' ), getTaskData( '2', 'copyedit' ) ];
	tasksStore.setTaskQueue( taskQueue );
	assert.strictEqual( tasksStore.backup, null );
	tasksStore.showNextTask();
	tasksStore.backupState();
	assert.deepEqual( tasksStore.backup, {
		taskQueue,
		currentTaskIndex: 1,
		taskCount: 2
	} );
} );

QUnit.test( 'should restore backed up states with restoreState', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	stubApiRequests( this.sandbox, tasksStore );
	const taskQueue = [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'copyedit' ),
		getTaskData( '3', 'copyedit' )
	];
	tasksStore.setTaskQueue( taskQueue );
	tasksStore.showNextTask();
	assert.deepEqual( tasksStore.getCurrentTask(), taskQueue[ 1 ] );
	tasksStore.backupState();
	tasksStore.showNextTask();
	assert.deepEqual( tasksStore.getCurrentTask(), taskQueue[ 2 ] );
	tasksStore.restoreState();
	assert.deepEqual( tasksStore.getCurrentTask(), taskQueue[ 1 ] );
	assert.deepEqual( tasksStore.getTaskQueue(), taskQueue );
} );
