'use strict';

// CodexModule generates codex.js at ResourceLoader time, so it does not exist
// here on disk for jest to resolve.
jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );

const { mount, flushPromises } = require( '@vue/test-utils' );
const WelcomeBackDialog = require( './WelcomeBackDialog.vue' );

const mockSaveOption = jest.fn();

/**
 * @param {boolean} open
 * @return {Object} the mounted wrapper
 */
function mountDialog( open ) {
	return mount( WelcomeBackDialog, {
		props: { open },
		global: { stubs: { teleport: true } },
	} );
}

describe( 'WelcomeBackDialog', () => {
	let wrapper;

	beforeEach( () => {
		mockSaveOption.mockReset().mockResolvedValue( {} );
		global.mw.Api = jest.fn( () => ( { saveOption: mockSaveOption } ) );
		global.mw.errorLogger = { logError: jest.fn() };
		mw.user.getName.mockReturnValue( 'Alice' );
		wrapper = mountDialog( true );
	} );

	it( 'saves that the user saw it when it mounts open', () => {
		expect( mockSaveOption ).toHaveBeenCalledTimes( 1 );
		expect( mockSaveOption ).toHaveBeenCalledWith( 'growthexperiments-home-intro-seen', 1 );
	} );

	it( 'saves nothing when it mounts closed', () => {
		mockSaveOption.mockClear();

		mountDialog( false );

		expect( mockSaveOption ).not.toHaveBeenCalled();
	} );

	it( 'saves nothing when the user closes it', async () => {
		mockSaveOption.mockClear();

		await wrapper.find( '.cdx-dialog__header__close-button' ).trigger( 'click' );

		expect( mockSaveOption ).not.toHaveBeenCalled();
	} );

	it( 'logs an error when the seen state is not saved', async () => {
		mockSaveOption.mockRejectedValue( 'http' );

		mountDialog( true );
		await flushPromises();

		expect( mw.errorLogger.logError ).toHaveBeenCalled();
	} );

	it( 'shows the user name in the title', () => {
		expect( mw.message ).toHaveBeenCalledWith( 'growthexperiments-home-welcome-back-title', 'Alice' );
	} );

	it( 'emits select-interests when the user clicks the button', async () => {
		await wrapper.find( '.growthexperiments-welcome-back-dialog__select-interests' ).trigger( 'click' );

		expect( wrapper.emitted( 'select-interests' ) ).toHaveLength( 1 );
	} );

	it( 'shows the button at the large size', () => {
		expect(
			wrapper.find( '.growthexperiments-welcome-back-dialog__select-interests' ).classes(),
		).toContain( 'cdx-button--size-large' );
	} );

	it( 'emits update:open when the user closes the dialog', async () => {
		await wrapper.find( '.cdx-dialog__header__close-button' ).trigger( 'click' );

		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );
} );
