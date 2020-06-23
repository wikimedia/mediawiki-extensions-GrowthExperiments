'use strict';

const pathToWidget = '../../../modules/homepage/suggestededits/ext.growthExperiments.Homepage.SuggestedEdits.ErrorCardWidget.js',
	sinon = require( 'sinon' );

QUnit.module( 'ErrorCardWidget', function () {
	QUnit.test( 'constructor', function ( assert ) {
		global.mw.message = sinon.stub().returns( {
			text: sinon.stub().returns( 'Stub text' )
		} );
		const ErrorCardWidget = require( pathToWidget );
		/* eslint-disable-next-line no-new */
		new ErrorCardWidget();
		assert.ok( true );
	} );
} );
