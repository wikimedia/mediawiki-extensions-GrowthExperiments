'use strict';

const PostEditDrawer = require( '../../../modules/ext.growthExperiments.PostEdit/PostEditDrawer.js' );
const PostEditPanel = require( '../../../modules/ext.growthExperiments.PostEdit/PostEditPanel.js' );
const HelpPanelLogger = require( '../../../modules/ext.growthExperiments.Help/HelpPanelLogger.js' );

QUnit.module( 'ext.growthExperiments.PostEdit/PostEditDrawer.js', QUnit.newMwEnvironment() );

QUnit.test( 'should log postedit-toast-message-impression when the toast message is shown', function ( assert ) {
	const postEditPanel = new PostEditPanel( {
		taskType: 'copyedit',
		taskState: 'saved'
	} );
	const helpPanelLogger = new HelpPanelLogger( true );
	sinon.spy( helpPanelLogger, 'log' );
	const drawer = new PostEditDrawer( postEditPanel, helpPanelLogger );
	drawer.showWithToastMessage();
	assert.deepEqual( helpPanelLogger.log.firstCall.args, [ 'postedit-toast-message-impression' ] );
} );

QUnit.test( 'should log postedit-expand when the drawer is expanded', function ( assert ) {
	const postEditPanel = new PostEditPanel( {
		taskType: 'copyedit',
		taskState: 'saved'
	} );
	const helpPanelLogger = new HelpPanelLogger( true );
	sinon.spy( helpPanelLogger, 'log' );
	const drawer = new PostEditDrawer( postEditPanel, helpPanelLogger );
	drawer.expand();
	assert.deepEqual( helpPanelLogger.log.firstCall.args, [ 'postedit-expand' ] );
} );

QUnit.test( 'should log postedit-collapse when the drawer is expanded', function ( assert ) {
	const postEditPanel = new PostEditPanel( {
		taskType: 'copyedit',
		taskState: 'saved'
	} );
	const helpPanelLogger = new HelpPanelLogger( true );
	sinon.spy( helpPanelLogger, 'log' );
	const drawer = new PostEditDrawer( postEditPanel, helpPanelLogger );
	drawer.collapse();
	assert.deepEqual( helpPanelLogger.log.firstCall.args, [ 'postedit-collapse' ] );
} );
