'use strict';

const MenteeOverviewPresets = require( '../../../../modules/ext.growthExperiments.MentorDashboard/MenteeOverview/MenteeOverviewPresets.js' );

QUnit.module( 'ext.growthExperiments.MentorDashboard/MenteeOverview/MenteeOverviewPresets.js', QUnit.newMwEnvironment() );

QUnit.test( 'getPreset missing value', function ( assert ) {
	const menteeOverviewPresets = new MenteeOverviewPresets();
	assert.strictEqual( menteeOverviewPresets.getPreset( 'foo' ), undefined );
} );

QUnit.test( 'getPresets uses defaults ', function ( assert ) {
	const menteeOverviewPresets = new MenteeOverviewPresets();
	assert.deepEqual( menteeOverviewPresets.getPresets(), {
		usersToShow: 10,
		filters: {
			minedits: 1,
			maxedits: 500
		}
	} );
} );

QUnit.test( 'setPreset overrides defaults', function ( assert ) {
	const menteeOverviewPresets = new MenteeOverviewPresets();
	assert.deepEqual( menteeOverviewPresets.getPresets(), {
		usersToShow: 10,
		filters: {
			minedits: 1,
			maxedits: 500
		}
	} );
	menteeOverviewPresets.setPreset( 'usersToShow', 'foo' );
	menteeOverviewPresets.setPreset( 'bar', { baz: 1 } );
	assert.deepEqual( menteeOverviewPresets.getPresets(), {
		bar: { baz: 1 },
		usersToShow: 'foo',
		filters: {
			minedits: 1,
			maxedits: 500
		}
	} );
} );

QUnit.test( 'setPreset requires a string', function ( assert ) {
	const menteeOverviewPresets = new MenteeOverviewPresets();
	assert.throws(
		function () {
			menteeOverviewPresets.setPreset( { foo: 'bar' }, 'value' );
		},
		/is not a string/
	);
} );

QUnit.test( 'getPreset requires a string', function ( assert ) {
	const menteeOverviewPresets = new MenteeOverviewPresets();
	assert.throws(
		function () {
			menteeOverviewPresets.getPreset( { foo: 'bar' } );
		},
		/is not a string/
	);
} );
