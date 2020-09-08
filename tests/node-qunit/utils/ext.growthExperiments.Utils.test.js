'use strict';

QUnit.module( 'ext.growthExperiments.Utils.js', {}, function () {
	const Utils = require( '../../../modules/utils/ext.growthExperiments.Utils.js' );
	QUnit.test( 'serializeActionData', function ( assert ) {
		assert.strictEqual( Utils.serializeActionData( null ), '' );
		assert.strictEqual( Utils.serializeActionData( { foo: 'bar', blah: 1 } ), 'foo=bar;blah=1' );
		assert.strictEqual( Utils.serializeActionData( [ 'abc', 'def', 'ghi' ] ), 'abc;def;ghi' );
		assert.strictEqual( Utils.serializeActionData( '' ), '' );
		assert.strictEqual( Utils.serializeActionData( 'foo' ), 'foo' );
		assert.strictEqual( Utils.serializeActionData( 42 ), 42 );
		assert.strictEqual( Utils.serializeActionData( true ), true );
	} );

	QUnit.test( 'isUserInVariant', function ( assert ) {
		global.mw = {};
		global.mw.user = {};
		global.mw.user.options = {};
		global.mw.user.options.values = {};
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'A';
		assert.strictEqual( Utils.isUserInVariant( 'C' ), false );
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'C';
		assert.strictEqual( Utils.isUserInVariant( 'C' ), true );
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'C';
		assert.strictEqual( Utils.isUserInVariant( [ 'C', 'D' ] ), true );
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'C';
		assert.strictEqual( Utils.isUserInVariant( [ 'C', 'D' ] ), true );
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'A';
		assert.strictEqual( Utils.isUserInVariant( 'CAT' ), false );
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'A';
		assert.strictEqual( Utils.isUserInVariant( [ 'CAT' ] ), false );
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'CAT';
		assert.strictEqual( Utils.isUserInVariant( 'A' ), false );
		global.mw.user.options.values[ 'growthexperiments-homepage-variant' ] = 'CAT';
		assert.strictEqual( Utils.isUserInVariant( [ 'A' ] ), false );
	} );
} );
