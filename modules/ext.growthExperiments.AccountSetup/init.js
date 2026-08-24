const Vue = require( 'vue' );
const Pinia = require( 'pinia' );

const pinia = Pinia.createPinia();
const AccountSetupApp = require( './AccountSetup.vue' );

const app = Vue.createMwApp( AccountSetupApp );
app.use( pinia );
app.provide( 'mwApi', new mw.Api() );
app.mount( '#growthexperiments-account_setup' );
