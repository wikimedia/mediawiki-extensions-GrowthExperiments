'use strict';

const NewcomerTasksStore = require( '../../../modules/ext.growthExperiments.DataStore/NewcomerTasksStore.js' );
const SuggestedEditsMobileSummary = require( '../../../modules/ext.growthExperiments.Homepage.mobile/SuggestedEditsMobileSummary.js' );
const rootStore = require( '../__mocks__/store.js' );
const store = {
	newcomerTasks: new NewcomerTasksStore( rootStore ),
	CONSTANTS: require( '../../../modules/ext.growthExperiments.DataStore/constants.js' ),
};

QUnit.module( 'ext.growthExperiments.Homepage.mobile/SuggestedEditsMobileSummary.js', QUnit.newMwEnvironment( {
	config: {
		homepagemodules: {
			'suggested-edits': {
				'task-preview': {
					title: 'Article title',
				},
			},
		},
		'wgGEHomepageModuleActionData-suggested-edits': {
			taskCount: 1,
		},
	},
	beforeEach() {
		this.sandbox.stub( mw.language, 'convertNumber', ( num ) => String( num ) );
	},
} ) );

QUnit.test( 'should show MobileNoTasksWidget if there is no task preview ', function ( assert ) {
	const done = assert.async();
	const getConfigStub = this.sandbox.stub( mw.config, 'get' );
	getConfigStub.withArgs( 'homepagemodules' ).returns( {
		'suggested-edits': {
		},
	} );
	getConfigStub.withArgs( 'wgGEHomepageModuleActionData-suggested-edits' ).returns( {
		taskCount: 0,
	} );
	const module = new SuggestedEditsMobileSummary( {
		$element: $( '<div>' ),
		newcomerTaskLogger: { log() {} },
		homepageModuleLogger: { log() {} },
	}, store );
	module.initialize().then( () => {
		assert.true( [ ...module.$content[ 0 ].classList ].includes( 'growthexperiments-suggestededits-mobilesummary-notasks-widget' ) );
		done();
	} );
} );

QUnit.test( 'should hide page views in SmallTaskCard if task preview is available', function ( assert ) {
	const done = assert.async();
	const task = {
		title: 'Article title',
		difficulty: 'easy',
		tasktype: 'copyedit',
		pageviews: 200,
	};
	this.sandbox.stub( store.newcomerTasks.api, 'getExtraDataFromPcs', ( taskData ) => {
		Object.assign( taskData, task );
		return $.Deferred().resolve( task ).promise();
	} );
	const module = new SuggestedEditsMobileSummary( {
		$element: $( '<div>' ),
		newcomerTaskLogger: { log() {} },
		homepageModuleLogger: { log() {} },
	}, store );
	module.initialize().then( () => {
		const $content = module.$content;
		assert.strictEqual( $content.find( '.mw-ge-small-task-card-pageviews' ).text(), '' );
		assert.strictEqual( $content.find( '.mw-ge-small-task-card-title' ).text(), task.title );
		done();
	} );
} );

QUnit.test( 'should show MobileNoTasksWidget for updateUiBasedOnState if there is no current task', ( assert ) => {
	const module = new SuggestedEditsMobileSummary( {
		$element: $( '<div>' ),
		newcomerTaskLogger: { log() {} },
		homepageModuleLogger: { log() {} },
	}, store );
	store.newcomerTasks.currentTaskIndex = -1;
	module.updateUiBasedOnState();
	assert.true( [ ...module.$content[ 0 ].classList ].includes( 'growthexperiments-suggestededits-mobilesummary-notasks-widget' ) );
} );

QUnit.test( 'should show the preview for the current task for updateUiBasedOnState', ( assert ) => {
	const module = new SuggestedEditsMobileSummary( {
		$element: $( '<div>' ),
		newcomerTaskLogger: { log() {} },
		homepageModuleLogger: { log() {} },
	}, store );
	const task = {
		title: 'Article title',
		difficulty: 'easy',
		tasktype: 'copyedit',
		pageviews: 200,
	};
	store.newcomerTasks.taskQueue = [ task ];
	store.newcomerTasks.currentTaskIndex = 0;
	module.updateUiBasedOnState();
	assert.strictEqual( module.$content.find( '.mw-ge-small-task-card-title' ).text(), task.title );
} );
