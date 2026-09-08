const Vue = require( 'vue' );
const Pinia = require( 'pinia' );

( async function () {
	const pinia = Pinia.createPinia();
	const AccountSetupApp = require( './AccountSetup.vue' );

	await mw.loader.using( [ 'ext.testKitchen' ] );
	const experiment = await mw.tk.getExperiment( 'de-1-3-1-specialhomepage-onboarding-ab-test' );

	const app = Vue.createMwApp( AccountSetupApp );
	app.use( pinia );
	app.provide( 'mwApi', new mw.Api() );

	app.provide( 'experiment', experiment );
	app.mount( '#growthexperiments-account_setup' );
}() );
