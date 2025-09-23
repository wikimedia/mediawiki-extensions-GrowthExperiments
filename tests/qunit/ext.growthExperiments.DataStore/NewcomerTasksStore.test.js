'use strict';

const NewcomerTasksStore = require( '../../../modules/ext.growthExperiments.DataStore/NewcomerTasksStore.js' );
const EVENT_TASK_QUEUE_CHANGED = 'taskQueueChanged';
const store = require( '../__mocks__/store.js' );

/**
 * Get mock task data
 *
 * @param {string} title
 * @param {string} tasktype
 * @param {number} [pageId]
 * @param {Object} qualityGateConfig
 *
 * @return {mw.libs.ge.TaskData}
 */
const getTaskData = ( title, tasktype, pageId, qualityGateConfig ) => ( {
	title,
	tasktype,
	difficulty: 'easy',
	qualityGateIds: [],
	qualityGateConfig: qualityGateConfig || {},
	url: null,
	token: 'token-' + title,
	pageId: pageId || Math.floor( Math.random() * 100 ),
} );

/**
 * Generate random task set data
 *
 * @param {number} n The number of tasks to generate
 * @param {Object|undefined|null} qualityConfig the quality gate config object associated with all tasks
 *
 * @return {mw.libs.ge.TaskData[]}
 */
const randomTaskSet = ( n, qualityConfig = {} ) => {
	const taskTypes = [
		'copyedit',
		'links',
		'references',
		'link-recommendation',
		'image-recommendation',
	];
	return Array( n ).fill( 0 ).map( ( _, i ) => {
		const taskType = taskTypes[ Math.floor( Math.random() * 100 ) % taskTypes.length ];
		return { ...getTaskData( `task-${ i }`, taskType ), ...qualityConfig };
	} );
};

const stubApiRequests = ( sandbox, tasksStore ) => {
	sandbox.stub( tasksStore, 'fetchMoreTasks' ).returns( $.Deferred().resolve() );
	sandbox.stub( tasksStore, 'preloadExtraDataForUpcomingTask' ).returns( $.Deferred().resolve() );
	sandbox.stub( tasksStore, 'fetchExtraDataForCurrentTask' ).returns( $.Deferred().resolve() );
};

QUnit.module( 'ext.growthExperiments.DataStore/NewcomerTasksStore.js', QUnit.newMwEnvironment( {
	beforeEach() {
		const getOptionsStub = this.sandbox.stub( mw.user.options, 'get' );
		getOptionsStub.withArgs( 'growthexperiments-homepage-se-filters' ).returns(
			JSON.stringify( [ 'copyedit' ] ),
		);
		getOptionsStub.withArgs( 'growthexperiments-homepage-se-ores-topic-filters' ).returns(
			JSON.stringify( [ 'architecture' ] ),
		);
	},
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
		getTaskData( '3', 'copyedit' ),
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
		getTaskData( '2', 'references' ),
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
		getTaskData( '2', 'references' ),
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
		getTaskData( '2', 'references' ),
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
		getTaskData( '2', 'references' ),
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
		getTaskData( '3', 'references' ),
	] );
	tasksStore.showNextTask();
	assert.strictEqual( stub.callCount, 1 );
	assert.strictEqual( stub.firstCall.args[ 0 ], 2 );
	tasksStore.showNextTask();
	// end of task queue reached so fetchExtraDataForTaskIndex should not be called
	assert.strictEqual( stub.callCount, 1 );
} );

QUnit.test( 'should emit an event when additional tasks are added to the task queue', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	const spy = this.sandbox.spy( tasksStore, 'emit' );
	tasksStore.addToTaskQueue( [ getTaskData( '1', 'copyedit' ) ] );
	assert.true( spy.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
} );

QUnit.test( 'should set the preloaded task in the task queue', ( assert ) => {
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

QUnit.test( 'should set the task queue and update the taskCount when preloaded tasks are lesser than the api page size', function ( assert ) {
	this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHomepageModuleActionData-suggested-edits' ).returns( {
		taskCount: 6,
	} );
	const tasksStore = new NewcomerTasksStore( store );
	const qualityConfig = { qualityGateConfig: { dailyLimit: 10 } };
	const spy = this.sandbox.spy( tasksStore, 'emit' );
	const preloadedTaskQueue = randomTaskSet( 5, qualityConfig );

	assert.deepEqual( tasksStore.getTaskCount(), 6 );
	assert.deepEqual( tasksStore.getTaskQueue(), [] );
	assert.deepEqual( tasksStore.getQualityGateConfig(), {} );
	assert.true( tasksStore.getCurrentTask() === undefined );
	tasksStore.setPreloadedTaskQueue( preloadedTaskQueue );
	assert.deepEqual( tasksStore.getTaskQueue(), preloadedTaskQueue );
	assert.deepEqual( tasksStore.getCurrentTask(), preloadedTaskQueue[ 0 ] );
	assert.deepEqual( tasksStore.getQualityGateConfig(), qualityConfig.qualityGateConfig );
	assert.deepEqual( tasksStore.getTaskCount(), 5 );
	assert.true( spy.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
} );
QUnit.test( 'should set the task queue and not update the taskCount when preloaded tasks are more than the api page size', function ( assert ) {
	this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHomepageModuleActionData-suggested-edits' ).returns( {
		taskCount: 31,
	} );
	const tasksStore = new NewcomerTasksStore( store );
	const qualityConfig = { qualityGateConfig: { dailyLimit: 10 } };
	const spy = this.sandbox.spy( tasksStore, 'emit' );
	const preloadedTaskQueue = randomTaskSet( 20, qualityConfig );

	assert.deepEqual( tasksStore.getTaskCount(), 31 );
	assert.deepEqual( tasksStore.getTaskQueue(), [] );
	assert.deepEqual( tasksStore.getQualityGateConfig(), {} );
	assert.true( tasksStore.getCurrentTask() === undefined );
	tasksStore.setPreloadedTaskQueue( preloadedTaskQueue );
	assert.deepEqual( tasksStore.getTaskQueue(), preloadedTaskQueue );
	assert.deepEqual( tasksStore.getCurrentTask(), preloadedTaskQueue[ 0 ] );
	assert.deepEqual( tasksStore.getQualityGateConfig(), qualityConfig.qualityGateConfig );
	assert.deepEqual( tasksStore.getTaskCount(), 31 );
	assert.true( spy.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
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
		taskCount: 2,
	} );
} );

QUnit.test( 'should restore backed up states with restoreState', function ( assert ) {
	const tasksStore = new NewcomerTasksStore( store );
	stubApiRequests( this.sandbox, tasksStore );
	const taskQueue = [
		getTaskData( '1', 'copyedit' ),
		getTaskData( '2', 'copyedit' ),
		getTaskData( '3', 'copyedit' ),
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

QUnit.module( 'Actions', () => {
	QUnit.module( 'Fetch tasks', () => {
		QUnit.test( 'should fetch tasks and update state using API response values', function ( assert ) {
			const done = assert.async();
			this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHomepageModuleActionData-suggested-edits' ).returns( {
				taskCount: 52,
			} );
			const tasksStore = new NewcomerTasksStore( store );
			const qualityConfig = { qualityGateConfig: { dailyLimit: 10 } };
			stubApiRequests( this.sandbox, tasksStore );
			assert.deepEqual( tasksStore.getTaskCount(), 52 );

			this.sandbox.stub( tasksStore.api, 'fetchTasks' ).returns( $.Deferred().resolve( {
				hasNext: true,
				tasks: randomTaskSet( 20, qualityConfig ),
				count: 51,
			} ) );
			this.sandbox.spy( tasksStore, 'emit' );
			tasksStore.fetchTasks( 'test' ).then( () => {
				assert.deepEqual( tasksStore.getTaskCount(), 51 );
				assert.true( tasksStore.emit.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
				done();
			} );
		} );
		QUnit.test( 'should fetch tasks and update the taskCount to the number of fetched tasks when the API returns less results than requested', function ( assert ) {
			const done = assert.async();
			this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHomepageModuleActionData-suggested-edits' ).returns( {
				taskCount: 52,
			} );
			const tasksStore = new NewcomerTasksStore( store );
			const qualityConfig = { qualityGateConfig: { dailyLimit: 10 } };
			stubApiRequests( this.sandbox, tasksStore );
			assert.deepEqual( tasksStore.getTaskCount(), 52 );

			this.sandbox.stub( tasksStore.api, 'fetchTasks' ).returns( $.Deferred().resolve( {
				hasNext: false,
				tasks: randomTaskSet( 2, qualityConfig ),
				count: 3,
			} ) );

			tasksStore.on( EVENT_TASK_QUEUE_CHANGED, () => {
				assert.deepEqual( tasksStore.getTaskCount(), 2 );
				done();
			} );

			tasksStore.fetchTasks( 'test' );
		} );

		QUnit.test( 'should pass the page ID to exclude in the API config if one is passed to fetchTasks', function ( assert ) {
			const done = assert.async();
			const tasksStore = new NewcomerTasksStore( store );
			stubApiRequests( this.sandbox, tasksStore );
			const fetchTasksStub = this.sandbox.stub( tasksStore.api, 'fetchTasks' );
			const excludePageId = 123;
			const tasks = [
				getTaskData( 'exclude', 'copyedit', excludePageId ),
				getTaskData( '1', 'copyedit' ),
				getTaskData( '2', 'copyedit' ),
			];
			fetchTasksStub.returns( $.Deferred().resolve( { tasks: tasks.slice( 1 ), count: 2 } ) );
			tasksStore.fetchTasks( 'test', { excludePageId } ).then( () => {
				assert.deepEqual( fetchTasksStub.firstCall.args[ 2 ], {
					context: 'test',
					excludePageIds: [ excludePageId ],
				} );
				assert.deepEqual( tasksStore.getTaskQueue(), tasks.slice( 1 ) );
				assert.strictEqual( tasksStore.getTaskCount(), 2 );
				done();
			} );
		} );
		QUnit.test( 'should filter out daily limit exceeded quota task types when excludeExceededQuotaTaskTypes is passed to fetchTasks',
			function ( assert ) {
				const done = assert.async();
				const tasksStore = new NewcomerTasksStore( store );
				stubApiRequests( this.sandbox, tasksStore );
				const fetchTasksStub = this.sandbox.stub( tasksStore.api, 'fetchTasks' );
				const qualityGateConfig = {
					'link-recommendation': { dailyLimit: false },
					'image-recommendation': { dailyLimit: true },
				};
				const tasks = [
					getTaskData( '1', 'copyedit', null, qualityGateConfig ),
					getTaskData( '2', 'copyedit', null, qualityGateConfig ),
					getTaskData( '3', 'link-recommendation', null, qualityGateConfig ),
					getTaskData( '4', 'link-recommendation', null, qualityGateConfig ),
					getTaskData( '5', 'image-recommendation', null, qualityGateConfig ),
				];
				fetchTasksStub.returns( $.Deferred().resolve( { tasks } ) );
				tasksStore.fetchTasks( 'test', { excludeExceededQuotaTaskTypes: true } ).then( () => {
					assert.deepEqual( tasksStore.getTaskQueue(), tasks.slice( 0, 4 ) );
					assert.strictEqual( tasksStore.getTaskCount(), 4 );
					done();
				} );
			} );
	} );

	QUnit.module( 'Fetch more tasks', () => {
		QUnit.test( 'should fetch tasks and update the taskCount to the number of fetched tasks when the API response informs there are no more results', function ( assert ) {
			const done = assert.async();
			this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHomepageModuleActionData-suggested-edits' ).returns( {
				taskCount: 32,
			} );
			const tasksStore = new NewcomerTasksStore( store );
			const qualityConfig = { qualityGateConfig: { dailyLimit: 10 } };
			assert.deepEqual( tasksStore.getTaskCount(), 32 );

			this.sandbox.stub( tasksStore, 'preloadExtraDataForUpcomingTask' ).returns( $.Deferred().resolve() );
			this.sandbox.stub( tasksStore, 'fetchExtraDataForCurrentTask' ).returns( $.Deferred().resolve() );
			this.sandbox.stub( tasksStore.api, 'fetchTasks' ).onCall( 0 ).returns( $.Deferred().resolve( {
				hasNext: true,
				tasks: randomTaskSet( 20, qualityConfig ),
				count: 32,
			} ) ).onCall( 1 ).returns( $.Deferred().resolve( {
				hasNext: false,
				tasks: randomTaskSet( 9, qualityConfig ),
				count: 12,
			} ) );

			this.sandbox.spy( tasksStore, 'emit' );
			tasksStore.fetchTasks( 'test' )
				.then( () => {
					assert.deepEqual( tasksStore.getTaskCount(), 32 );
					assert.true( tasksStore.emit.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
				} )
				.then( () => tasksStore.fetchMoreTasks( 'test' ) )
				.then( () => {
					assert.deepEqual( tasksStore.getTaskCount(), 29 );
					assert.true( tasksStore.emit.calledWith( EVENT_TASK_QUEUE_CHANGED ) );
					done();
				} );
		} );
	} );
} );
