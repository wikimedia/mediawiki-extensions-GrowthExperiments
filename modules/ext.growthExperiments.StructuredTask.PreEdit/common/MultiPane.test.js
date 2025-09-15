'use strict';
const { mount } = require( '@vue/test-utils' );
const MultiPane = require( './MultiPane.vue' );

const steps = {
	step1: '<p>This is step 1</p>',
	step2: '<p>This is step 2</p>',
	step3: '<p>This is step 3</p>',
};

const renderComponent = ( props, slots ) => {
	const defaultProps = { currentStep: 1 };
	const wrapper = mount( MultiPane, {
		props: Object.assign( {}, defaultProps, props ),
		slots: Object.assign( {}, slots ),
	} );
	return wrapper;
};

const swipeToRight = ( wrapper ) => wrapper.trigger( 'touchstart', {
	touches: [ { clientX: 100, clientY: 400 } ],
} )
	.then( () => wrapper.trigger( 'touchmove', {
		touches: [ { clientX: 150, clientY: 400 } ],
	} ) );

const swipeToLeft = ( wrapper ) => wrapper.trigger( 'touchstart', {
	touches: [ { clientX: 100, clientY: 400 } ],
} )
	.then( () => wrapper.trigger( 'touchmove', {
		touches: [ { clientX: 50, clientY: 400 } ],
	} ) );

describe( 'MultiPane', () => {
	it( 'should render default slot content', () => {
		const wrapper = renderComponent( {}, { default: '<h3>Multipane component</h3>' } );
		expect( wrapper.text() ).toContain( 'Multipane component' );
	} );

	it( 'should render steps when slot content provided', () => {
		const wrapper = renderComponent( {}, steps );
		expect( wrapper.text() ).toContain( 'This is step 1' );
	} );

	it( 'should react to current step prop changes', () => {
		const wrapper = renderComponent( {}, steps );
		expect( wrapper.text() ).toContain( 'This is step 1' );
		wrapper.setProps( { currentStep: 2 } )
			.then( () => expect( wrapper.text() ).toContain( 'This is step 2' ) )
			.then( () => wrapper.setProps( { currentStep: 1 } ) )
			.then( () => expect( wrapper.text() ).toContain( 'This is step 1' ) );
	} );

	it( 'should emit update:currentStep event on touch events', () => {
		const wrapper = renderComponent( { totalSteps: 3 }, steps );
		swipeToLeft( wrapper )
			.then( () => {
				expect( wrapper.emitted() ).toHaveProperty( 'update:currentStep' );
				expect( wrapper.emitted( 'update:currentStep' ) ).toEqual( [ [ 2 ] ] );
			} );
	} );

	it( 'currentStep should react to left swipe gestures and navigate next', () => {
		const wrapper = renderComponent(
			{
				currentStep: 2,
				totalSteps: 3,
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } ),
			},
			steps,
		);
		swipeToLeft( wrapper ).then( () => {
			expect( wrapper.vm.$props.currentStep ).toBe( 3 );
			expect( wrapper.text() ).toContain( 'step 3' );
		} );
	} );

	it( 'currentStep prop value should react to rigth swipe gestures and navigate back', () => {
		const wrapper = renderComponent(
			{
				currentStep: 2,
				totalSteps: 3,
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } ),
			},
			steps,
		);
		swipeToRight( wrapper )
			.then( () => {
				expect( wrapper.vm.$props.currentStep ).toBe( 1 );
				expect( wrapper.text() ).toContain( 'step 1' );
			} );
	} );

	it( 'should not react to right swipe gestures if there is no prev step', () => {
		const wrapper = renderComponent(
			{
				totalSteps: 3,
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } ),
			},
			steps,
		);
		swipeToRight( wrapper )
			.then( () => {
				expect( wrapper.vm.$props.currentStep ).toBe( 1 );
				expect( wrapper.text() ).toContain( 'step 1' );
			} );
	} );

	it( 'should not react to left swipe gestures if there is no next step', () => {
		const wrapper = renderComponent(
			{
				currentStep: 3,
				totalSteps: 3,
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } ),
			},
			steps,
		);
		swipeToLeft( wrapper )
			.then( () => {
				expect( wrapper.vm.$props.currentStep ).toBe( 3 );
				expect( wrapper.text() ).toContain( 'step 3' );
			} );
	} );

	it( 'should apply correct transition name on swipe to right', () => {
		const wrapper = renderComponent(
			{
				currentStep: 2,
				totalSteps: 3,
			},
			steps,
		);
		swipeToRight( wrapper )
			.then( () => {
				expect( wrapper.vm.computedTransitionName ).toBe( 'left' );
			} );
	} );
	it( 'should apply correct transition name on swipe to left', () => {
		const wrapper = renderComponent(
			{
				currentStep: 1,
				totalSteps: 3,
			},
			steps,
		);
		swipeToLeft( wrapper )
			.then( () => {
				expect( wrapper.vm.computedTransitionName ).toBe( 'right' );
			} );
	} );
} );
