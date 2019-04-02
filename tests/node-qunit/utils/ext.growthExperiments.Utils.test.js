QUnit.module( 'ext.growthExperiments.Utils.js', {}, function () {
	QUnit.test( 'serializeActionData', function ( assert ) {
		var Utils = require( '../../../modules/utils/ext.growthExperiments.Utils.js' );
		assert.strictEqual( Utils.serializeActionData( null ), '' );
		assert.strictEqual( Utils.serializeActionData( { foo: 'bar', blah: 1 } ), 'foo=bar;blah=1' );
		assert.strictEqual( Utils.serializeActionData( [ 'abc', 'def', 'ghi' ] ), 'abc;def;ghi' );
		assert.strictEqual( Utils.serializeActionData( '' ), '' );
		assert.strictEqual( Utils.serializeActionData( 'foo' ), 'foo' );
		assert.strictEqual( Utils.serializeActionData( 42 ), 42 );
		assert.strictEqual( Utils.serializeActionData( true ), true );
	} );
} );
