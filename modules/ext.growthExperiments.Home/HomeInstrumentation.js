/**
 * Register the instrumentation with the Home health metrics instrument.
 *
 * This does nothing on other pages, when the PersonalDashboard module group override is
 * active (test traffic), or when TestKitchen is not installed.
 *
 * @param {mw.testKitchen.GenericInstrumentation} instrumentation
 */
function useHomeInstrument( instrumentation ) {
	if (
		mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Home' ||
		mw.config.get( 'wgPersonalDashboardPdoActive', false )
	) {
		return;
	}
	mw.loader.using( 'ext.testKitchen' ).then( () => {
		mw.testKitchen.getInstrument( 'home-health-metrics' ).use( instrumentation );
	}, () => {
		// TestKitchen is optional.
	} );
}

module.exports = { useHomeInstrument };
