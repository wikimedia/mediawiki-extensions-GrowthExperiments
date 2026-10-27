'use strict';

const { useHomeInstrument } = require( './HomeInstrumentation.js' );

/**
 * Let the pending promise callbacks run.
 *
 * @return {Promise<void>}
 */
function settle() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 0 );
	} );
}

describe( 'useHomeInstrument', () => {
	let instrument;

	/**
	 * @param {Object} config
	 */
	function setConfig( config ) {
		mw.config.get.mockImplementation(
			( key, fallback ) => ( key in config ? config[ key ] : fallback ),
		);
	}

	beforeEach( () => {
		instrument = { use: jest.fn() };
		mw.testKitchen.getInstrument = jest.fn().mockReturnValue( instrument );
		mw.loader.using.mockResolvedValue( undefined );
	} );

	afterEach( () => {
		delete mw.testKitchen.getInstrument;
		mw.config.get.mockReset();
		mw.loader.using.mockReset();
		mw.loader.using.mockResolvedValue( undefined );
	} );

	it( 'registers the instrumentation with the Home instrument on Special:Home', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Home' } );
		const instrumentation = jest.fn();

		useHomeInstrument( instrumentation );
		await settle();

		expect( mw.loader.using ).toHaveBeenCalledWith( 'ext.testKitchen' );
		expect( mw.testKitchen.getInstrument ).toHaveBeenCalledWith( 'home-health-metrics' );
		expect( instrument.use ).toHaveBeenCalledWith( instrumentation );
	} );

	it( 'does nothing on other pages', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Homepage' } );

		useHomeInstrument( jest.fn() );
		await settle();

		expect( mw.loader.using ).not.toHaveBeenCalled();
		expect( instrument.use ).not.toHaveBeenCalled();
	} );

	it( 'does nothing when the PersonalDashboard override is active', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Home', wgPersonalDashboardPdoActive: true } );

		useHomeInstrument( jest.fn() );
		await settle();

		expect( mw.loader.using ).not.toHaveBeenCalled();
		expect( instrument.use ).not.toHaveBeenCalled();
	} );

	it( 'does nothing when TestKitchen is not installed', async () => {
		setConfig( { wgCanonicalSpecialPageName: 'Home' } );
		mw.loader.using.mockRejectedValue( new Error( 'Unknown module: ext.testKitchen' ) );

		useHomeInstrument( jest.fn() );
		await settle();

		expect( instrument.use ).not.toHaveBeenCalled();
	} );
} );
