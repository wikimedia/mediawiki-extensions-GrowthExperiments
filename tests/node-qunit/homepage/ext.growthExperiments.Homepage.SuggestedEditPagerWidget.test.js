'use strict';

const pathToWidget = '../../../modules/homepage/suggestededits/ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js';

QUnit.module( 'SuggestedEditPagerWidget', function () {
	QUnit.test( 'constructor', function ( assert ) {
		const SuggestedEditPagerWidget = require( pathToWidget );
		/* eslint-disable-next-line no-new */
		new SuggestedEditPagerWidget();
		assert.ok( true );
	} );
} );
