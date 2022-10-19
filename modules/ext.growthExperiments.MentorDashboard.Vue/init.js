( function () {
	'use strict';
	const Vue = require( 'vue' );
	const Vuex = require( 'vuex' );
	const store = require( './store/index.js' );
	const { convertNumber } = require( '../utils/filters.js' );

	// TODO create an App.vue root component to wrap more Mentor modules as they appear
	const MenteeOverview = require( './components/MenteeOverview/MenteeOverview.vue' );

	Vue.use( Vuex );

	const app = Vue.createMwApp( MenteeOverview );

	app.use( store )
		.mount( '#vue-root' );

	app.config.globalProperties.$filters = {
		convertNumber
	};

}() );
