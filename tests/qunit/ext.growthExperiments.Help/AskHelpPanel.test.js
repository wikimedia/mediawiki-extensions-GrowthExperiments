'use strict';

const AskHelpPanel = require( '../../../modules/ext.growthExperiments.Help/AskHelpPanel.js' );
const expectedPanelProperties = {
	askSource: 'string',
	logger: 'object',
	storageKey: 'string',
	questionPosterAllowIncludingTitle: 'boolean',
	panelTitleMessages: 'object',
	$askhelpHeader: 'object',
	questionCompleteConfirmationText: 'string',
	viewQuestionText: 'string',
	submitFailureMessage: 'string',
	askhelpTextInput: 'object',
	questionIncludeTitleCheckbox: 'object',
	questionIncludeFieldLayout: 'object',
};

const expectedMentorMessageKeys = [
	'growthexperiments-homepage-mentorship-dialog-title',
	'growthexperiments-help-panel-questioncomplete-title',
	'growthexperiments-homepage-mentorship-questionreview-header-mentor-talk-link-text',
	'growthexperiments-homepage-mentorship-questionreview-header',
	'growthexperiments-homepage-mentorship-confirmation-text',
	'growthexperiments-homepage-mentorship-view-question-text',
	'growthexperiments-help-panel-question-post-error',
];

QUnit.module( 'ext.growthExperiments.Help/AskHelpPanel.js', QUnit.newMwEnvironment( {
	beforeEach() {
		this.sandbox.stub( mw.user, 'getName' ).returns( 'Name' );
		this.sandbox.stub( mw.Title, 'newFromText' ).returns( {
			getUrl() {
				return 'fake url';
			},
		} );
	},
} ) );

QUnit.test( 'AskHelpPanel from mentor-homepage', function ( assert ) {
	const spy = this.sandbox.spy( mw, 'message' );
	const panel = new AskHelpPanel( {
		askSource: 'mentor-homepage',
		relevantTitle: null,
		logger: { log: function () {} },
	} );
	Object.keys( expectedPanelProperties ).forEach( ( propertyName ) => {
		assert.strictEqual( typeof panel[ propertyName ], expectedPanelProperties[ propertyName ] );
	} );
	assert.strictEqual( panel.questionPosterAllowIncludingTitle, false );
	expectedMentorMessageKeys.forEach( ( messageKey ) => {
		assert.strictEqual( spy.calledWith( messageKey ), true );
	} );
} );

QUnit.test( 'AskHelpPanel from mentor-helppanel', function ( assert ) {
	this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHelpPanelMentorData' ).returns( {
		effectiveName: 'Mentor Name',
		effectiveGender: 'Mentor Gender',
		name: 'Mentor Name',
		gender: 'Mentor Gender',
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	const panel = new AskHelpPanel( {
		askSource: 'mentor-helppanel',
		relevantTitle: null,
		logger: { log: function () {} },
	} );
	Object.keys( expectedPanelProperties ).forEach( ( propertyName ) => {
		assert.strictEqual( typeof panel[ propertyName ], expectedPanelProperties[ propertyName ] );
	} );
	assert.strictEqual( panel.questionPosterAllowIncludingTitle, true );
	expectedMentorMessageKeys.forEach( ( messageKey ) => {
		assert.strictEqual( spy.calledWith( messageKey ), true );
	} );
} );

QUnit.test( 'AskHelpPanel from mentor-helppanel with away mentor', function ( assert ) {
	this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHelpPanelMentorData' ).returns( {
		effectiveName: 'Effective Mentor Name',
		effectiveGender: 'Effective Mentor Gender',
		name: 'Mentor Name',
		gender: 'Mentor Gender',
		backAt: '14 December 2022',
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	// eslint-disable-next-line no-new
	new AskHelpPanel( {
		askSource: 'mentor-helppanel',
		relevantTitle: null,
		logger: { log: function () {} },
	} );
	assert.strictEqual( spy.calledWith(
		'growthexperiments-homepage-mentorship-questionreview-header-away',
	), true );
} );

QUnit.test( 'AskHelpPanel from mentor-helppanel with indefinitely away mentor', function ( assert ) {
	this.sandbox.stub( mw.config, 'get' ).withArgs( 'wgGEHelpPanelMentorData' ).returns( {
		effectiveName: 'Effective Mentor Name',
		effectiveGender: 'Effective Mentor Gender',
		name: 'Mentor Name',
		gender: 'Mentor Gender',
		backAt: null,
	} );
	const spy = this.sandbox.spy( mw, 'message' );
	// eslint-disable-next-line no-new
	new AskHelpPanel( {
		askSource: 'mentor-helppanel',
		relevantTitle: null,
		logger: { log: function () {} },
	} );
	assert.strictEqual( spy.calledWith(
		'growthexperiments-homepage-mentorship-questionreview-header-away-no-timestamp',
	), true );
} );

QUnit.test( 'AskHelpPanel from helpdesk', function ( assert ) {
	const spy = this.sandbox.spy( mw, 'message' );
	const panel = new AskHelpPanel( {
		askSource: 'helpdesk',
		relevantTitle: null,
		logger: { log: function () {} },
	} );
	Object.keys( expectedPanelProperties ).forEach( ( propertyName ) => {
		assert.strictEqual( typeof panel[ propertyName ], expectedPanelProperties[ propertyName ] );
	} );
	assert.strictEqual( panel.questionPosterAllowIncludingTitle, true );
	const expectedMessageKeys = [
		'growthexperiments-help-panel-questionreview-title',
		'growthexperiments-help-panel-questionreview-header',
		'growthexperiments-help-panel-questioncomplete-confirmation-text',
		'growthexperiments-help-panel-questioncomplete-view-link-text',
		'growthexperiments-help-panel-question-post-error',
	];
	expectedMessageKeys.forEach( ( messageKey ) => {
		assert.strictEqual( spy.calledWith( messageKey ), true );
	} );
} );
