'use strict';

const PostEditToastMessage = require( '../../../modules/ext.growthExperiments.PostEdit/PostEditToastMessage.js' );

QUnit.module( 'ext.growthExperiments.PostEdit/PostEditToastMessage.js', QUnit.newMwEnvironment() );

QUnit.test( 'should construct a MessageWidget', function ( assert ) {
	const message = new PostEditToastMessage( { label: 'foo', type: 'notice' } );
	assert.true( message instanceof OO.ui.MessageWidget );
} );

QUnit.test( 'should auto-hide if autoHideDuration is set', function ( assert ) {
	const autoHideDuration = 1;
	const message = new PostEditToastMessage( { label: 'foo', type: 'notice', autoHideDuration } );
	const done = assert.async();
	const timeout = setTimeout( function () {
		assert.true( message.isHidden );
		done();
	}, autoHideDuration );
	return function () {
		done();
		clearTimeout( timeout );
	};
} );
