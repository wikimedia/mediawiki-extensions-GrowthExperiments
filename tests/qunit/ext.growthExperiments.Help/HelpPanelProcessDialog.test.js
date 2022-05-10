'use strict';
const TaskTypesAbFilter = require( '../../../modules/ext.growthExperiments.DataStore/TaskTypesAbFilter.js' );

const linkRecommendationSuggestedEditSession = {
	taskType: 'link-recommendation',
	active: true,
	connect: function () {},
	save: function () {}
};
const copyEditRecommendationSuggestedEditSession = {
	taskType: 'copyedit',
	active: true,
	connect: function () {},
	save: function () {}
};

let HelpPanelProcessDialog;

QUnit.module( 'ext.growthExperiments.Help/HelpPanelProcessDialog.js', QUnit.newMwEnvironment( {
	beforeEach() {
		this.sandbox.stub( TaskTypesAbFilter, 'getTaskTypes' ).returns( {} );
		HelpPanelProcessDialog = require( '../../../modules/ext.growthExperiments.Help/HelpPanelProcessDialog.js' );
	}
} ) );

QUnit.test( 'getDefaultPanelForSuggestedEditSession for link-recommendation', function ( assert ) {
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		suggestedEditSession: linkRecommendationSuggestedEditSession
	} );
	assert.strictEqual( helpPanelProcessDialog.getDefaultPanelForSuggestedEditSession(), 'suggested-edits' );
} );

QUnit.test( 'getDefaultPanelForSuggestedEditSession for copyedit', function ( assert ) {
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		suggestedEditSession: copyEditRecommendationSuggestedEditSession
	} );
	assert.strictEqual( helpPanelProcessDialog.getDefaultPanelForSuggestedEditSession(), undefined );
} );

QUnit.test( 'updateEditMode for link-recommendation', function ( assert ) {
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		suggestedEditSession: linkRecommendationSuggestedEditSession,
		logger: {
			isEditing: function () {
				return true;
			},
			getEditor: function () {
				return 'visual';
			}
		}
	} );
	const windowManager = new OO.ui.WindowManager( { modal: false } );
	windowManager.addWindows( [ helpPanelProcessDialog ] );
	this.sandbox.stub( helpPanelProcessDialog, 'updateMode' );
	this.sandbox.spy( helpPanelProcessDialog, 'swapPanel' );
	helpPanelProcessDialog.updateEditMode();
	assert.true( helpPanelProcessDialog.swapPanel.notCalled );
} );

QUnit.test( 'updateEditMode for copyedit, isEditing', function ( assert ) {
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		suggestedEditSession: copyEditRecommendationSuggestedEditSession,
		logger: {
			isEditing: function () {
				return true;
			},
			getEditor: function () {
				return 'visual';
			}
		}
	} );
	const windowManager = new OO.ui.WindowManager( { modal: false } );
	windowManager.addWindows( [ helpPanelProcessDialog ] );
	this.sandbox.stub( helpPanelProcessDialog, 'updateMode' );
	this.sandbox.stub( helpPanelProcessDialog, 'swapPanel' );
	helpPanelProcessDialog.updateEditMode();
	assert.true( helpPanelProcessDialog.swapPanel.calledWith( 'home' ) );
} );
