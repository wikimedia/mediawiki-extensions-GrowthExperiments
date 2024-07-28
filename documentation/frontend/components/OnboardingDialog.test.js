import { vi, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import OnboardingDialog from './OnboardingDialog.vue';

const steps = {
	step1: '<p>This is step 1</p>',
	step2: '<p>This is step 2</p>',
	step3: '<p>This is step 3</p>'
};

const renderComponent = ( props, slots ) => {
	const defaultProps = { open: false, showPaginator: false };
	const defaultSlots = { title: '<h3>Onboarding title</h3>' };
	const wrapper = mount( OnboardingDialog, {
		props: Object.assign( {}, defaultProps, props ),
		slots: Object.assign( {}, defaultSlots, slots ),
		global: {
			mocks: {
				$i18n: vi.fn( ( x, ...params ) => ( {
					text: vi.fn( () => `${ params.join( ' of ' ) }` )
				} ) )
			}
		}
	} );
	return wrapper;
};

describe( 'Onboarding dialog', () => {
	it( 'should open the dialog based on "open" prop state', () => {
		const wrapper = renderComponent();
		expect( wrapper.text() ).to.not.contain( 'Onboarding title' );
		wrapper.setProps( { open: true } ).then( () => {
			expect( wrapper.text() ).to.contain( 'Onboarding title' );
		} );
	} );

	it( 'should render content passed as default slot', () => {
		const wrapper = renderComponent(
			{ open: true },
			{
				default: '<p>This is the default content</p>'
			} );
		expect( wrapper.text() ).to.contain( 'This is the default content' );
		expect( wrapper.text() ).not.to.contain( 'This is step 1' );
	} );

	it( 'should render content passed as step', () => {
		const wrapper = renderComponent( { open: true, totalSteps: 2 }, steps );
		expect( wrapper.text() ).not.to.contain( 'This is the default content' );
		expect( wrapper.text() ).to.contain( 'This is step 1' );
	} );

	it( 'should render content passed to closeBtnText slot', () => {
		const wrapper = renderComponent(
			{ open: true, totalSteps: 2 },
			{ closeBtnText: 'Skip all', step1: '<p>This is step 1</p>' } );
		expect( wrapper.text() ).to.contain( 'Skip all' );
	} );

	it( 'should render content passed to checkboxLabel slot', () => {
		const wrapper = renderComponent(
			{ open: true },
			{
				checkboxLabel: "Don't show again"
			} );
		expect( wrapper.text() ).to.contain( "Don't show again" );
	} );

	it( 'should render content passed as step with paginator', () => {
		const wrapper = renderComponent( {
			open: true,
			totalSteps: 2,
			stepperLabel: '1 of 2'
		}, steps );
		expect( wrapper.text() ).toContain( '1 of 2' );
	} );

	it( 'should not render paginator if dialog has only one step', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, totalSteps: 1 },
			{
				default: '<p>This is the default content</p>'
			} );
		expect( wrapper.text() ).not.to.contain( '1 of 3' );
	} );

	it( 'should render a icon only header button when there is only one step', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, totalSteps: 1 },
			{
				default: '<p>This is the default content</p>'
			} );
		expect( wrapper.findAll( 'button[aria-label="close"]' ) ).toHaveLength( 1 );
	} );

	it( 'should display paginator if dialog includes more than one step', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, stepperLabel: '1 of 3', totalSteps: 3 },
			steps
		);
		expect( wrapper.text() ).toContain( '1 of 3' );
	} );

	it( 'should render the first step as informed in initialStep prop', () => {
		const wrapper = renderComponent(
			{ initialStep: 3, open: true, totalSteps: 3 },
			steps
		);
		expect( wrapper.text() ).to.contain( 'This is step 3' );
		expect( wrapper.text() ).not.to.contain( 'This is step 1' );
	} );

	it( 'should render content passed to startBtnText slot', () => {
		const wrapper = renderComponent(
			{ initialStep: 2, open: true, totalSteps: 2 },
			{
				startBtnText: 'Empezar'
			} );
		expect( wrapper.text() ).not.to.contain( 'Get started' );
		expect( wrapper.text() ).to.contain( 'Empezar' );
	} );

	it( 'should navigate to the next step on click next button', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, totalSteps: 3 },
			steps
		);
		const buttonNext = wrapper.get( '[aria-label="next"]' );
		buttonNext.trigger( 'click' )
			.then( () => expect( wrapper.text() ).to.contain( 'This is step 2' ) );

	} );

	it( 'should navigate back on click previous button', () => {
		const wrapper = renderComponent(
			{ initialStep: 2, open: true, totalSteps: 3 },
			steps
		);
		const buttonNext = wrapper.get( '[aria-label="previous"]' );
		buttonNext.trigger( 'click' )
			.then( () => expect( wrapper.text() ).not.to.contain( 'This is step 2' ) )

			.then( () => expect( wrapper.text() ).to.contain( 'This is step 1' ) );

	} );

	it( 'should return a result when closing', () => {
		const wrapper = renderComponent(
			{
				open: true,
				totalSteps: 3,
				'onUpdate:open': ( newVal ) => wrapper.setProps( { open: newVal } ),
				'onUpdate:is-checked': ( newVal ) => wrapper.setProps( { isChecked: newVal } )
			},
			Object.assign( {}, steps, { checkboxLabel: 'Check me', headerBtnText: 'Skip' } )
		);

		// In step 1, check checkbox
		wrapper.get( '[type="checkbox"]' ).setValue( true )
			// Forward to step 2
			.then( () => wrapper.get( '[aria-label="next"]' ).trigger( 'click' ) )
			// Forward to step 3
			.then( () => wrapper.get( '[aria-label="next"]' ).trigger( 'click' ) )
			// Backwards to step 2
			.then( () => wrapper.get( '[aria-label="previous"]' ).trigger( 'click' ) )
			// Click on "Skip"
			.then( () => wrapper.get( '.ext-growthExperiments-OnboardingDialog__header__top > button' ).trigger( 'click' ) )
			.then( () => {
				expect( wrapper.emitted() ).toHaveProperty( 'close' );
				expect( wrapper.emitted().close ).toMatchObject( [ [
					{
						closeSource: 'quiet',
						currentStep: 2,
						greaterStep: 3,
						isChecked: true
					}
				] ] );
			} );
	} );
} );
