var pathToWidget = '../../../modules/homepage/suggestededits/ext.growthExperiments.Homepage.SuggestedEdits.ErrorCardWidget.js',
	sinon = require( 'sinon' ),
	ErrorCardWidget;

QUnit.module( 'ErrorCardWidget', function () {
	QUnit.test( 'constructor', function ( assert ) {
		global.mw.message = sinon.stub().returns( {
			text: sinon.stub().returns( 'Stub text' )
		} );
		ErrorCardWidget = require( pathToWidget );
		/* eslint-disable-next-line no-new */
		new ErrorCardWidget();
		assert.ok( true );
	} );
} );
