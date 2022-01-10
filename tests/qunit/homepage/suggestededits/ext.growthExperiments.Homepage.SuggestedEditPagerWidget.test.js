'use strict';

const pathToWidget = '../../../../modules/homepage/suggestededits/ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js';
const SuggestedEditPagerWidget = require( pathToWidget );

let sandbox, suggestedEditPagerWidget;

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js', QUnit.newMwEnvironment( {
	beforeEach() {
		sandbox = sinon.sandbox.create();
		suggestedEditPagerWidget = new SuggestedEditPagerWidget();
	},

	afterEach() {
		sandbox.restore();
	}
} ) );

QUnit.test( 'constructor', function ( assert ) {
	assert.true( new SuggestedEditPagerWidget() instanceof SuggestedEditPagerWidget );
} );

QUnit.test( 'setMessage with currentPosition < totalCount', function ( assert ) {
	const spy = sandbox.spy( mw, 'message' );
	suggestedEditPagerWidget.setMessage( 1, 2 );
	assert.true( spy.calledWithExactly( 'growthexperiments-homepage-suggestededits-pager', '1', '2' ) );
} );

QUnit.test( 'setMessage with currentPosition === totalCount', function ( assert ) {
	const spy = sandbox.spy( mw, 'message' );
	suggestedEditPagerWidget.setMessage( 2, 2 );
	assert.true( spy.calledWithExactly(
		'growthexperiments-homepage-suggestededits-pager',
		'2',
		'2'
	) );
} );

QUnit.test( 'setMessage with currentPosition > totalCount', function ( assert ) {
	const spy = sandbox.spy( mw, 'message' );
	suggestedEditPagerWidget.setMessage( 3, 2 );
	assert.true( spy.getCall( 0 ).calledWithExactly( 'growthexperiments-homepage-suggestededits-pager-end' ) );
	assert.true( spy.getCall( 1 ).calledWithExactly(
		'growthexperiments-homepage-suggestededits-pager',
		'⧼growthexperiments-homepage-suggestededits-pager-end⧽',
		'2'
	) );
} );
