'use strict';
const FiltersWidget = require( 'ext.growthExperiments.Homepage.SuggestedEdits/FiltersButtonGroupWidget.js' );
const HomepageModuleLogger = require( '../../../modules/ext.growthExperiments.Homepage.Logger/index.js' );
const rootStore = require( '../__mocks__/store.js' );

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEdits/FiltersButtonGroupWidget.js', QUnit.newMwEnvironment( {} ) );

QUnit.test( 'should log only topicfilter impressions', function ( assert ) {
	const logger = new HomepageModuleLogger( true, 'some-token' );
	this.sandbox.stub( logger, 'log' );

	const widget = new FiltersWidget( {
		topicMatching: true,
		useTopicMatchMode: false,
		mode: 'some-mode',
	}, logger, rootStore );

	widget.topicFilterButtonWidget.emit( 'click' );

	assert.true( logger.log.calledOnce );
	assert.deepEqual( logger.log.getCall( 0 ).args, [
		'suggested-edits',
		'some-mode',
		'se-topicfilter-open',
		{
			topics: [],
		},
	] );

} );

QUnit.test( 'should log topicmatchmode impression', function ( assert ) {
	const logger = new HomepageModuleLogger( true, 'some-token' );
	this.sandbox.stub( logger, 'log' );

	const widget = new FiltersWidget( {
		topicMatching: true,
		useTopicMatchMode: true,
		mode: 'some-mode',
	}, logger, rootStore );

	widget.topicFilterButtonWidget.emit( 'click' );

	assert.deepEqual( logger.log.getCall( 0 ).args, [
		'suggested-edits',
		'some-mode',
		'se-topicfilter-open',
		{
			topics: [],
		},
	] );
	assert.deepEqual( logger.log.getCall( 1 ).args, [
		'suggested-edits',
		'some-mode',
		'se-topicmatchmode-impression',
	] );
	widget.topicFiltersDialog.topicSelector.emit( 'toggleMatchMode', 'AND' );
	assert.deepEqual( logger.log.getCall( 2 ).args, [
		'suggested-edits',
		'some-mode',
		'se-topicmatchmode-and',
		{
			topicsMatchMode: 'AND',
		},
	] );
} );
