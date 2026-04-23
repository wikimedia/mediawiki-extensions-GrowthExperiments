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

QUnit.test( 'getUserVariantForLegacySchema returns wgGEDefaultUserVariant when string', ( assert ) => {
	mw.config.set( 'wgGEDefaultUserVariant', 'control' );
	mw.config.set( 'wgTestKitchenUserExperiments', null );
	assert.strictEqual( Utils.getUserVariantForLegacySchema(), 'control' );

	mw.config.set( 'wgGEDefaultUserVariant', 'treatment' );
	assert.strictEqual( Utils.getUserVariantForLegacySchema(), 'treatment' );
} );

QUnit.test( 'getUserVariantForLegacySchema returns unsampled when no config', ( assert ) => {
	mw.config.set( 'wgGEDefaultUserVariant', undefined );
	mw.config.set( 'wgTestKitchenUserExperiments', null );
	assert.strictEqual( Utils.getUserVariantForLegacySchema(), 'unsampled' );
} );

QUnit.test( 'getUserVariantForLegacySchema returns unsampled when wgGEDefaultUserVariant is not string', ( assert ) => {
	mw.config.set( 'wgGEDefaultUserVariant', { foo: 'bar' } );
	mw.config.set( 'wgTestKitchenUserExperiments', null );
	assert.strictEqual( Utils.getUserVariantForLegacySchema(), 'unsampled' );
} );

QUnit.test( 'getUserVariantForLegacySchema returns experimentName:variant when TestKitchen enrolled', ( assert ) => {
	const savedTestKitchen = mw.testKitchen;
	mw.testKitchen = {
		compat: {
			getExperiment: ( name ) => ( {
				getAssignedGroup: () => ( name === 'growthexperiments-homepage-welcome' ? 'treatment' : null ),
			} ),
		},
	};
	mw.config.set( 'wgTestKitchenUserExperiments', {
		// eslint-disable-next-line camelcase
		active_experiments: [
			{ name: 'growthexperiments-homepage-welcome', config: { coordinator: 'custom' } },
		],
	} );
	assert.strictEqual(
		Utils.getUserVariantForLegacySchema(),
		'growthexperiments-homepage-welcome:treatment',
		'returns experiment name and variant when enrolled',
	);
	mw.testKitchen = savedTestKitchen;
} );

QUnit.test( 'getUserVariantForLegacySchema returns first experiment when non-Growth is first', ( assert ) => {
	const savedTestKitchen = mw.testKitchen;
	mw.testKitchen = {
		compat: {
			getExperiment: ( name ) => ( {
				getAssignedGroup: () => ( name === 'wikimedia-mobile-experiment' ? 'variantB' : null ),
			} ),
		},
	};
	mw.config.set( 'wgTestKitchenUserExperiments', {
		// eslint-disable-next-line camelcase
		active_experiments: [
			{ name: 'wikimedia-mobile-experiment', config: {} },
			{ name: 'growthexperiments-homepage-welcome', config: {} },
		],
	} );
	assert.strictEqual(
		Utils.getUserVariantForLegacySchema(),
		'wikimedia-mobile-experiment:variantB',
		'returns first experiment from list regardless of name (no growthexperiments filter)',
	);
	mw.testKitchen = savedTestKitchen;
} );

QUnit.test( 'getUserVariantForLegacySchema returns all experiments when multiple enrolled', ( assert ) => {
	const savedTestKitchen = mw.testKitchen;
	mw.testKitchen = {
		compat: {
			getExperiment: ( name ) => ( {
				getAssignedGroup: () => {
					if ( name === 'growthexperiments-homepage-welcome' ) {
						return 'treatment';
					}
					if ( name === 'growthexperiments-suggested-edits' ) {
						return 'control';
					}
					return null;
				},
			} ),
		},
	};
	mw.config.set( 'wgTestKitchenUserExperiments', {
		// eslint-disable-next-line camelcase
		active_experiments: [
			{ name: 'growthexperiments-homepage-welcome', config: {} },
			{ name: 'growthexperiments-suggested-edits', config: {} },
		],
	} );
	assert.strictEqual(
		Utils.getUserVariantForLegacySchema(),
		'growthexperiments-homepage-welcome:treatment;growthexperiments-suggested-edits:control',
		'returns all enrolled experiments and variants joined by semicolon',
	);
	mw.testKitchen = savedTestKitchen;
} );

QUnit.test( 'getUserVariantForLegacySchema returns unsampled when all experiments have null variant', ( assert ) => {
	const savedTestKitchen = mw.testKitchen;
	mw.testKitchen = {
		compat: {
			getExperiment: () => ( { getAssignedGroup: () => null } ),
		},
	};
	mw.config.set( 'wgTestKitchenUserExperiments', {
		// eslint-disable-next-line camelcase
		active_experiments: [
			{ name: 'growthexperiments-homepage-welcome', config: {} },
			{ name: 'other-experiment', config: {} },
		],
	} );
	assert.strictEqual(
		Utils.getUserVariantForLegacySchema(),
		'unsampled',
		'skips experiments with null variant, returns unsampled when parts is empty',
	);
	mw.testKitchen = savedTestKitchen;
} );

QUnit.test( 'getUserVariantForLegacySchema skips experiments with null variant', ( assert ) => {
	const savedTestKitchen = mw.testKitchen;
	mw.testKitchen = {
		compat: {
			getExperiment: ( name ) => ( {
				getAssignedGroup: () => {
					if ( name === 'exp-a' ) {
						return 'treatment';
					}
					if ( name === 'exp-c' ) {
						return 'control';
					}
					return null;
				},
			} ),
		},
	};
	mw.config.set( 'wgTestKitchenUserExperiments', {
		// eslint-disable-next-line camelcase
		active_experiments: [
			{ name: 'exp-a', config: {} },
			{ name: 'exp-b', config: {} },
			{ name: 'exp-c', config: {} },
		],
	} );
	assert.strictEqual(
		Utils.getUserVariantForLegacySchema(),
		'exp-a:treatment;exp-c:control',
		'only includes experiments with string variant, skips exp-b',
	);
	mw.testKitchen = savedTestKitchen;
} );

QUnit.test( 'getUserVariantForLegacySchema truncates when over USER_VARIANT_MAX_LENGTH', ( assert ) => {
	const savedTestKitchen = mw.testKitchen;
	// Each "exp-xxx...n:variant" is ~174 chars; 3 experiments = 524 chars > 512
	const longName = ( n ) => 'exp-' + 'x'.repeat( 160 ) + n;
	mw.testKitchen = {
		compat: {
			getExperiment: () => ( { getAssignedGroup: () => 'variant' } ),
		},
	};
	mw.config.set( 'wgTestKitchenUserExperiments', {
		// eslint-disable-next-line camelcase
		active_experiments: [
			{ name: longName( 1 ), config: {} },
			{ name: longName( 2 ), config: {} },
			{ name: longName( 3 ), config: {} },
		],
	} );
	const result = Utils.getUserVariantForLegacySchema();
	assert.strictEqual( result.length, 512, 'truncated to USER_VARIANT_MAX_LENGTH' );
	mw.testKitchen = savedTestKitchen;
} );
