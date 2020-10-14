'use strict';

const pathToWidget = '../../../../modules/homepage/suggestededits/ext.growthExperiments.Homepage.SuggestedEdits.ErrorCardWidget.js';

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEdits.ErrorCardWidget.js', QUnit.newMwEnvironment() );

QUnit.test( 'constructor', function ( assert ) {
	const ErrorCardWidget = require( pathToWidget );
	/* eslint-disable-next-line no-new */
	new ErrorCardWidget();
	assert.ok( true );
} );
