'use strict';
const { mount } = require( '@vue/test-utils' );
jest.mock( './codex-icons.json', () => ( {
	cdxIconClose: 'Some truthy icon',
	cdxIconNext: '',
	cdxIconPrevious: '',
} ), { virtual: true } );
const OnboardingDialog = require( './OnboardingDialog.vue' );

const steps = {
	step1: '<p>This is step 1</p>',
	step2: '<p>This is step 2</p>',
	step3: '<p>This is step 3</p>',
};

const renderComponent = ( props, slots ) => {
	const defaultProps = {
		open: false,
		showPaginator: false,
		closeButtonText: 'Close',
		startButtonText: 'Get started',
	};
	const defaultSlots = { title: '<h3>Onboarding title</h3>' };
	const wrapper = mount( OnboardingDialog, {
		props: Object.assign( {}, defaultProps, props ),
		slots: Object.assign( {}, defaultSlots, slots ),
		global: {
			mocks: {
				$i18n: jest.fn( ( x, ...params ) => ( {
					text: jest.fn( () => `${ params.join( ' of ' ) }` ),
				} ) ),
			},
			stubs: {
				teleport: true,
			},
		},
		attachTo: document.body,
	} );
	return wrapper;
};

describe( 'Onboarding dialog', () => {
	it( 'should open the dialog based on "open" prop state', async () => {
		const wrapper = renderComponent();
		expect( wrapper.text() ).not.toContain( 'Onboarding title' );
		await wrapper.setProps( { open: true } );
		expect( wrapper.text() ).toContain( 'Onboarding title' );
	} );

	it( 'should render content passed as default slot', () => {
		const wrapper = renderComponent(
			{ open: true },
			{
				default: '<p>This is the default content</p>',
			} );
		expect( wrapper.text() ).toContain( 'This is the default content' );
		expect( wrapper.text() ).not.toContain( 'This is step 1' );
	} );

	it( 'should render content passed as step', () => {
		const wrapper = renderComponent( { open: true, totalSteps: 2 }, steps );
		expect( wrapper.text() ).not.toContain( 'This is the default content' );
		expect( wrapper.text() ).toContain( 'This is step 1' );
	} );

	it( 'should render content passed to closeBtnText prop', () => {
		const wrapper = renderComponent(
			{ open: true, totalSteps: 2, closeButtonText: 'Skip all' },
			{
				step1: '<p>This is step 1</p>',
			} );
		expect( wrapper.text() ).toContain( 'Skip all' );
	} );

	it( 'should render content passed to checkboxLabel prop', () => {
		const wrapper = renderComponent(
			{
				open: true,
				checkboxLabel: "Don't show again",
			},
		);
		expect( wrapper.text() ).toContain( "Don't show again" );
	} );

	it( 'should render content passed as step with paginator', () => {
		const wrapper = renderComponent( {
			open: true,
			totalSteps: 2,
			stepperLabel: '1 of 2',
		}, steps );
		expect( wrapper.text() ).toContain( '1 of 2' );
	} );

	it( 'should not render paginator if dialog has only one step', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, totalSteps: 1 },
			{
				default: '<p>This is the default content</p>',
			} );
		expect( wrapper.text() ).not.toContain( '1 of 3' );
	} );

	it( 'should render a icon only header button when there is only one step', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, totalSteps: 1 },
			{
				default: '<p>This is the default content</p>',
			} );
		expect( wrapper.findAll( 'button[aria-label="close"]' ) ).toHaveLength( 1 );
	} );

	it( 'should display paginator if dialog includes more than one step', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, stepperLabel: '1 of 3', totalSteps: 3 },
			steps,
		);
		expect( wrapper.text() ).toContain( '1 of 3' );
	} );

	it( 'should render the first step as informed in initialStep prop', () => {
		const wrapper = renderComponent(
			{ initialStep: 3, open: true, totalSteps: 3 },
			steps,
		);
		expect( wrapper.text() ).toContain( 'This is step 3' );
		expect( wrapper.text() ).not.toContain( 'This is step 1' );
	} );

	it( 'should render content passed to startBtnText prop', () => {
		const wrapper = renderComponent(
			{ initialStep: 2, open: true, totalSteps: 2, startButtonText: 'Empezar' },
		);
		expect( wrapper.text() ).not.toContain( 'Get started' );
		expect( wrapper.text() ).toContain( 'Empezar' );
	} );

	it( 'should navigate to the next step on click next button', () => {
		const wrapper = renderComponent(
			{ initialStep: 1, open: true, totalSteps: 3 },
			steps,
		);
		const buttonNext = wrapper.get( '[aria-label="next"]' );
		buttonNext.trigger( 'click' )
			.then( () => expect( wrapper.text() ).toContain( 'This is step 2' ) );

	} );

	it( 'should navigate back on click previous button', () => {
		const wrapper = renderComponent(
			{ initialStep: 2, open: true, totalSteps: 3 },
			steps,
		);
		const buttonNext = wrapper.get( '[aria-label="previous"]' );
		buttonNext.trigger( 'click' )
			.then( () => expect( wrapper.text() ).not.toContain( 'This is step 2' ) )

			.then( () => expect( wrapper.text() ).toContain( 'This is step 1' ) );

	} );

	it( 'should return a result when closing', () => {
		const wrapper = renderComponent(
			{
				open: true,
				totalSteps: 3,
				headerButtonText: 'Skip',
				checkboxLabel: "Don't show again",
				'onUpdate:open': ( newVal ) => wrapper.setProps( { open: newVal } ),
				'onUpdate:is-checked': ( newVal ) => wrapper.setProps( { isChecked: newVal } ),
			},
			steps,
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
			.then( () => wrapper.get( '.ext-growthExperiments-OnboardingDialog__header__top__button' ).trigger( 'click' ) )
			.then( () => {
				expect( wrapper.emitted() ).toHaveProperty( 'close' );
				expect( wrapper.emitted().close ).toMatchObject( [ [
					{
						closeSource: 'quiet',
						currentStep: 2,
						greaterStep: 3,
						isChecked: true,
					},
				] ] );
			} );
	} );
} );
