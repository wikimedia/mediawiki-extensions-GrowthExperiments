'use strict';

const Utils = require( '../../../modules/utils/Utils.js' );

QUnit.module( 'ext.growthExperiments.Utils.js', QUnit.newMwEnvironment() );

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
	this.sandbox.stub( mw.config, 'get' );
	mw.config.get.withArgs( 'wgGEUserVariants' ).returns( [ 'A', 'B', 'C', 'D', 'CAT' ] );
	mw.config.get.withArgs( 'wgGEDefaultUserVariant' ).returns( 'A' );
	this.sandbox.stub( mw.user.options, 'get' );
	const get = mw.user.options.get.withArgs( 'growthexperiments-homepage-variant' );

	get.returns( 'A' );
	assert.strictEqual( Utils.isUserInVariant( 'C' ), false );

	get.returns( 'C' );
	assert.strictEqual( Utils.isUserInVariant( 'C' ), true );

	get.returns( 'C' );
	assert.strictEqual( Utils.isUserInVariant( [ 'C', 'D' ] ), true );

	get.returns( 'D' );
	assert.strictEqual( Utils.isUserInVariant( [ 'C', 'D' ] ), true );

	get.returns( 'A' );
	assert.strictEqual( Utils.isUserInVariant( 'CAT' ), false );

	get.returns( 'A' );
	assert.strictEqual( Utils.isUserInVariant( [ 'CAT' ] ), false );

	get.returns( 'CAT' );
	assert.strictEqual( Utils.isUserInVariant( 'A' ), false );

	get.returns( 'CAT' );
	assert.strictEqual( Utils.isUserInVariant( [ 'A' ] ), false );

	get.returns( 'X' );
	assert.strictEqual( Utils.isUserInVariant( [ 'X' ] ), false );
	assert.strictEqual( Utils.isUserInVariant( [ 'A' ] ), true );
} );
