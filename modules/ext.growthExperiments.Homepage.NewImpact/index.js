( function () {
	'use strict';
	const Vue = require( 'vue' );
	const Vuex = require( 'vuex' );
	const NewImpact = require( './components/NewImpact.vue' );
	Vue.use( Vuex );
	Vue.createMwApp( NewImpact )
		.mount( '#new-impact-vue-root' );
}() );
