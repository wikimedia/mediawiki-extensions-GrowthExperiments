'use strict';

const PostEditDrawer = require( 'ext.growthExperiments.PostEdit/PostEditDrawer.js' );
const PostEditPanel = require( 'ext.growthExperiments.PostEdit/PostEditPanel.js' );
const HelpPanelLogger = require( '../../../modules/utils/HelpPanelLogger.js' );
const NewcomerTasksStore = require( '../../../modules/ext.growthExperiments.DataStore/NewcomerTasksStore.js' );

QUnit.module( 'ext.growthExperiments.PostEdit/PostEditDrawer.js', QUnit.newMwEnvironment() );

QUnit.test( 'should log postedit-toast-message-impression when the toast message is shown', ( assert ) => {
	const postEditPanel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskType: 'copyedit',
		taskState: 'saved',
	} );
	const helpPanelLogger = new HelpPanelLogger();
	sinon.spy( helpPanelLogger, 'log' );
	const drawer = new PostEditDrawer( postEditPanel, helpPanelLogger );
	drawer.showWithToastMessage();
	assert.deepEqual( helpPanelLogger.log.firstCall.args, [ 'postedit-toast-message-impression' ] );
} );

QUnit.test( 'should log postedit-expand when the drawer is expanded', ( assert ) => {
	const postEditPanel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskType: 'copyedit',
		taskState: 'saved',
	} );
	const helpPanelLogger = new HelpPanelLogger();
	sinon.spy( helpPanelLogger, 'log' );
	const drawer = new PostEditDrawer( postEditPanel, helpPanelLogger );
	drawer.expand();
	assert.deepEqual( helpPanelLogger.log.firstCall.args, [ 'postedit-expand' ] );
} );

QUnit.test( 'should log postedit-collapse when the drawer is expanded', ( assert ) => {
	const postEditPanel = new PostEditPanel( {
		newcomerTasksStore: new NewcomerTasksStore( {} ),
		taskType: 'copyedit',
		taskState: 'saved',
	} );
	const helpPanelLogger = new HelpPanelLogger();
	sinon.spy( helpPanelLogger, 'log' );
	const drawer = new PostEditDrawer( postEditPanel, helpPanelLogger );
	drawer.collapse();
	assert.deepEqual( helpPanelLogger.log.firstCall.args, [ 'postedit-collapse' ] );
} );
