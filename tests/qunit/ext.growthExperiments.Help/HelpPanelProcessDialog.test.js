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

QUnit.test( 'getDefaultPanelForSuggestedEditSession for link-recommendation', ( assert ) => {
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		suggestedEditSession: linkRecommendationSuggestedEditSession
	} );
	assert.strictEqual( helpPanelProcessDialog.getDefaultPanelForSuggestedEditSession(), 'suggested-edits' );
} );

QUnit.test( 'getDefaultPanelForSuggestedEditSession for copyedit', ( assert ) => {
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		suggestedEditSession: copyEditRecommendationSuggestedEditSession
	} );
	assert.strictEqual( helpPanelProcessDialog.getDefaultPanelForSuggestedEditSession(), undefined );
} );

QUnit.test( 'updateEditMode for link-recommendation', function ( assert ) {
	mw.config.set( 'wgGEShouldShowHelpPanelTaskQuickTips', true );
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		taskTypeId: 'link-recommendation',
		taskTypeData: {
			id: 'link-recommendation'
		},
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
	assert.strictEqual( helpPanelProcessDialog.suggestededitsPanel.preferredEditor, 'machineSuggestions' );
} );

QUnit.test( 'updateEditMode for copyedit, isEditing', function ( assert ) {
	mw.config.set( 'wgGEShouldShowHelpPanelTaskQuickTips', true );
	const helpPanelProcessDialog = new HelpPanelProcessDialog( {
		taskTypeId: 'copyedit',
		taskTypeData: {
			id: 'copyedit'
		},
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
	assert.strictEqual( helpPanelProcessDialog.suggestededitsPanel.preferredEditor, 'visualeditor' );
} );
