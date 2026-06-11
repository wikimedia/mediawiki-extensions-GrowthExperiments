'use strict';
const FiltersWidget = require( 'ext.growthExperiments.Homepage.SuggestedEdits/FiltersButtonGroupWidget.js' );
const rootStore = require( '../__mocks__/store.js' );

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEdits/FiltersButtonGroupWidget.js', QUnit.newMwEnvironment( {} ) );

QUnit.test( 'can be constructed with topic matching but without match mode', ( assert ) => {
	const widget = new FiltersWidget( {
		topicMatching: true,
		useTopicMatchMode: false,
		mode: 'some-mode',
	}, rootStore );

	assert.true( widget.topicFilterButtonWidget !== undefined );
} );

QUnit.test( 'can be constructed with topic matching and match mode', ( assert ) => {
	const widget = new FiltersWidget( {
		topicMatching: true,
		useTopicMatchMode: true,
		mode: 'some-mode',
	}, rootStore );

	assert.true( widget.topicFilterButtonWidget !== undefined );
} );
