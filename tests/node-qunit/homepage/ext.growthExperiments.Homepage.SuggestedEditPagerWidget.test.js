var pathToWidget = '../../../modules/homepage/suggestededits/ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js',
	SuggestedEditPagerWidget;

QUnit.module( 'SuggestedEditPagerWidget', function () {
	QUnit.test( 'constructor', function ( assert ) {
		SuggestedEditPagerWidget = require( pathToWidget );
		/* eslint-disable-next-line no-new */
		new SuggestedEditPagerWidget();
		assert.ok( true );
	} );
} );
