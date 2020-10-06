'use strict';

const Utils = require( '../../../modules/utils/ext.growthExperiments.Utils.js' );

QUnit.module( 'ext.growthExperiments.Utils.js', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.mwConfig = mw.config;
	},
	afterEach: function () {
		mw.config = this.mwConfig;
	}
} ) );

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
	const prefName = 'wgGEHomepageUserVariant';
	mw.config.get = sinon.stub();

	mw.config.get.withArgs( prefName ).returns( 'A' );
	assert.strictEqual( Utils.isUserInVariant( 'C' ), false );

	mw.config.get.withArgs( prefName ).returns( 'C' );
	assert.strictEqual( Utils.isUserInVariant( 'C' ), true );

	mw.config.get.withArgs( prefName ).returns( 'C' );
	assert.strictEqual( Utils.isUserInVariant( [ 'C', 'D' ] ), true );

	mw.config.get.withArgs( prefName ).returns( 'D' );
	assert.strictEqual( Utils.isUserInVariant( [ 'C', 'D' ] ), true );

	mw.config.get.withArgs( prefName ).returns( 'A' );
	assert.strictEqual( Utils.isUserInVariant( 'CAT' ), false );

	mw.config.get.withArgs( prefName ).returns( 'A' );
	assert.strictEqual( Utils.isUserInVariant( [ 'CAT' ] ), false );

	mw.config.get.withArgs( prefName ).returns( 'CAT' );
	assert.strictEqual( Utils.isUserInVariant( 'A' ), false );

	mw.config.get.withArgs( prefName ).returns( 'CAT' );
	assert.strictEqual( Utils.isUserInVariant( [ 'A' ] ), false );
} );
