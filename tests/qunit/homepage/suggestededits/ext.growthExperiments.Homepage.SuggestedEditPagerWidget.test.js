'use strict';

const pathToWidget = '../../../../modules/homepage/suggestededits/ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js';

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js', QUnit.newMwEnvironment() );

QUnit.test( 'constructor', function ( assert ) {
	const SuggestedEditPagerWidget = require( pathToWidget );
	/* eslint-disable-next-line no-new */
	new SuggestedEditPagerWidget();
	assert.ok( true );
} );
