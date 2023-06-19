import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FilterDialog from './FilterDialog.vue';

const renderComponent = ( props, slots ) => {
	const wrapper = mount( FilterDialog, {
		props: Object.assign( {}, props ),
		slots: Object.assign( {}, {
			title: 'Select',
			taskCount: '30 articles found',
			taskCountLoading: '... articles found'
		}, slots )
	} );
	return wrapper;
};

describe( 'FilterDialog', () => {
	it( 'should open the dialog based on "open" prop state and render content passed to slots', () => {
		const wrapper = renderComponent();
		wrapper.setProps( { open: true } ).then( () => {
			expect( wrapper.html ).toMatchSnapshot();
		} );
	} );

	it( 'should react to isLoading changes', () => {
		const wrapper = renderComponent( { open: true } );
		wrapper.setProps( { isLoading: true } ).then( () => {
			expect( wrapper.text() ).toContain( '... articles found' );
		} );
	} );

	it( 'doneBtn should be disabled when isLoading', () => {
		const wrapper = renderComponent(
			{ open: true },
			{
				doneBtn: 'Done'
			} );
		const doneBtn = wrapper.get( '[aria-label="done"]' );
		expect( doneBtn.attributes() ).not.to.haveOwnProperty( 'disabled' );
		wrapper.setProps( { isLoading: true } ).then( () => {
			expect( doneBtn.attributes() ).to.haveOwnProperty( 'disabled' );
		} );
	} );

	it( 'should emit close event with correct value when click on close btn', () => {
		const wrapper = renderComponent( { open: true } );
		const closeBtn = wrapper.get( '[ aria-label="close"]' );
		closeBtn.trigger( 'click' )
			.then( () => {
				expect( wrapper.emitted() ).toHaveProperty( 'close' );
				expect( wrapper.emitted().close ).toMatchObject( [ [ { closeSource: 'cancel' } ] ] );
			} );
	} );
	it( 'should emit close event with correct value when click on done btn', () => {
		const wrapper = renderComponent( { open: true } );
		const closeBtn = wrapper.get( '[ aria-label="done"]' );
		closeBtn.trigger( 'click' )
			.then( () => {
				expect( wrapper.emitted() ).toHaveProperty( 'close' );
				expect( wrapper.emitted().close ).toMatchObject( [ [ { closeSource: 'done' } ] ] );
			} );
	} );
} );
