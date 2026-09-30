const Vue = require( 'vue' );
const Pinia = require( 'pinia' );
const sendOnboardingReadyEvent = require( '../utils/sendOnboardingReadyEvent.js' );

( async function () {
	const platform = mw.config.get( 'skin' ) === 'minerva' ? 'mobile' : 'desktop';
	const wiki = mw.config.get( 'wgDBname' );
	function trackPhase( phase ) {
		mw.track(
			'stats.mediawiki_GrowthExperiments_account_setup_phase_seconds',
			performance.now(),
			{ phase, platform, wiki },
		);
	}

	trackPhase( 'module_executed' );
	const pinia = Pinia.createPinia();
	const AccountSetupApp = require( './AccountSetup.vue' );

	await mw.loader.using( [ 'ext.testKitchen' ] );
	const experiment = await mw.tk.getExperiment( 'de-1-3-1-specialhomepage-onboarding-ab-test' );
	trackPhase( 'testkitchen_ready' );

	const app = Vue.createMwApp( AccountSetupApp );
	app.use( pinia );
	app.provide( 'mwApi', new mw.Api() );

	app.provide( 'experiment', experiment );
	app.mount( '#growthexperiments-account_setup' );
	trackPhase( 'mounted' );
	const mountedMs = performance.now();
	mw.track(
		'stats.mediawiki_GrowthExperiments_onboarding_ready_seconds',
		mountedMs,
		{ group: 'treatment', platform, wiki },
	);
	sendOnboardingReadyEvent( experiment, mountedMs );
}() );
