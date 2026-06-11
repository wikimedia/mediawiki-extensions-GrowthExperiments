'use strict';

const Utils = require( '../../../modules/utils/Utils.js' );

QUnit.module( 'ext.growthExperiments.Utils.js', QUnit.newMwEnvironment() );

QUnit.test( 'serializeActionData', ( assert ) => {
	assert.strictEqual( Utils.serializeActionData( null ), '' );
	assert.strictEqual( Utils.serializeActionData( { foo: 'bar', blah: 1 } ), 'foo=bar;blah=1' );
	assert.strictEqual( Utils.serializeActionData( [ 'abc', 'def', 'ghi' ] ), 'abc;def;ghi' );
	assert.strictEqual( Utils.serializeActionData( '' ), '' );
	assert.strictEqual( Utils.serializeActionData( 'foo' ), 'foo' );
	assert.strictEqual( Utils.serializeActionData( 42 ), 42 );
	assert.strictEqual( Utils.serializeActionData( true ), true );
} );
