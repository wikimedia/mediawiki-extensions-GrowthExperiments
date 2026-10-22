const Vue = require( 'vue' );
const Pinia = require( 'pinia' );
const sendOnboardingReadyEvent = require( '../utils/sendOnboardingReadyEvent.js' );

( async function () {
	const platform = mw.config.get( 'skin' ) === 'minerva' ? 'mobile' : 'desktop';
	const wiki = mw.config.get( 'wgDBname' );
	function trackStep( step, durationMs ) {
		mw.track(
			'stats.mediawiki_GrowthExperiments_account_setup_step_seconds',
			durationMs,
			{ step, platform, wiki },
		);
	}

	const moduleExecutedMs = performance.now();
	const navigationEntry = performance.getEntriesByType( 'navigation' )[ 0 ];
	if ( navigationEntry && navigationEntry.responseStart > 0 ) {
		trackStep( 'first_byte', navigationEntry.responseStart );
		trackStep( 'first_byte_to_module', moduleExecutedMs - navigationEntry.responseStart );
	}

	const pinia = Pinia.createPinia();
	const AccountSetupApp = require( './AccountSetup.vue' );

	let experiment;
	try {
		await mw.loader.using( [ 'ext.testKitchen' ] );
		experiment = await mw.tk.getExperiment( 'de-1-3-1-specialhomepage-onboarding-ab-test' );
	} catch ( _ignored ) {
		experiment = { send: () => {} };
	}

	const testKitchenReadyMs = performance.now();
	trackStep( 'testkitchen_wait', testKitchenReadyMs - moduleExecutedMs );

	const app = Vue.createMwApp( AccountSetupApp );
	app.use( pinia );
	app.provide( 'mwApi', new mw.Api() );

	app.provide( 'experiment', experiment );
	app.mount( '#growthexperiments-account_setup' );
	if ( mw.config.get( 'wgGEAccountSetupOnHome' ) ) {
		// mw.Api runs option saves in sequence. This save ends before the skip or finish saves.
		new mw.Api().saveOption( 'growthexperiments-home-intro-seen', 1 ).catch( ( error ) => {
			mw.errorLogger.logError(
				new Error( 'AccountSetup: failed to save intro seen: ' + error ),
				'error.growthexperiments',
			);
		} );
	}
	const mountedMs = performance.now();
	trackStep( 'mount', mountedMs - testKitchenReadyMs );
	mw.track(
		'stats.mediawiki_GrowthExperiments_onboarding_ready_seconds',
		mountedMs,
		{ group: mw.config.get( 'wgGEAccountSetupOnHome' ) ? 'home' : 'treatment', platform, wiki },
	);
	sendOnboardingReadyEvent( experiment, mountedMs );
}() );
