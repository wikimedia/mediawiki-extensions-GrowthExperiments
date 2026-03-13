'use strict';

const useInstrument = require( '../../../modules/ext.growthExperiments.Homepage.Logger/useInstrument.js' );

QUnit.module( 'ext.growthExperiments.Homepage.Logger/useInstrument.js', QUnit.newMwEnvironment( {
	config: {},
	beforeEach: function () {
		this.submitSpy = this.sandbox.stub();
		this.instrument = { submitInteraction: this.submitSpy };
		// Direct assignment (mw.testKitchen may not exist in test env; Sinon cannot stub non-existent properties)
		this.savedEventLog = mw.eventLog;
		this.savedTestKitchen = mw.testKitchen;
		mw.eventLog = {
			newInstrument: () => this.instrument,
		};
		mw.testKitchen = {
			getExperiment: ( name ) => ( {
				getAssignedGroup: () => ( name === 'growthexperiments-homepage-welcome' ? 'treatment' : null ),
				config: {
					coordinator: 'default',
					assigned: 'treatment',
					enrolled: 'growthexperiments-homepage-welcome',
				},
			} ),
			getAssignments: () => ( {
				'growthexperiments-homepage-welcome': 'treatment',
			} ),
		};
	},
	afterEach: function () {
		mw.eventLog = this.savedEventLog;
		mw.testKitchen = this.savedTestKitchen;
	},
} ) );

QUnit.test( 'logEvent does not call submitInteraction when mw.testKitchen is absent', function ( assert ) {
	const submitSpy = this.sandbox.stub();
	mw.eventLog = { newInstrument: () => ( { submitInteraction: submitSpy } ) };
	mw.testKitchen = undefined;

	const { logEvent } = useInstrument( 'test', '/schema/1' );
	logEvent( 'impression', null, 'suggested-edits', null );

	assert.strictEqual( submitSpy.called, false, 'submitInteraction not called when testKitchen absent' );
} );

QUnit.test( 'logEvent calls submitInteraction with experiment data when enrolled', function ( assert ) {
	const { logEvent } = useInstrument( 'test', '/schema/1' );
	logEvent( 'impression', null, 'suggested-edits', null );

	assert.strictEqual( this.submitSpy.calledOnce, true );
	assert.strictEqual( this.submitSpy.firstCall.args[ 0 ], 'impression' );
	const interactionData = this.submitSpy.firstCall.args[ 1 ];
	assert.notStrictEqual( interactionData.experiment, undefined, 'experiment data is present' );
	assert.strictEqual( interactionData.experiment.assigned, 'treatment' );
	assert.strictEqual( interactionData.experiment.enrolled, 'growthexperiments-homepage-welcome' );
	assert.strictEqual( interactionData.action_source, 'suggested-edits' );
} );
