jest.mock( '../icons.json', () => ( {} ), { virtual: true } );
const { shallowMount, mount } = require( '@vue/test-utils' );
const DataTableCellValue = require( './DataTableCellValue.vue' );

describe( 'DataTableCellValue', () => {
	it( 'renders the value and adds align css class', () => {
		const wrapper = shallowMount( DataTableCellValue, {
			props: {
				value: 10,
				align: 'center'
			}
		} );

		expect( wrapper.text() ).toContain( '10' );
		const div = wrapper.find( 'div' );
		expect( div.classes() ).toContain( 'data-table-cell-value--align-center' );
	} );
	it( 'renders the given slot', () => {
		const wrapper = mount( DataTableCellValue, {
			slots: {
				default: 'Main Content'
			}
		} );

		expect( wrapper.text() ).toContain( 'Main Content' );

	} );
} );
