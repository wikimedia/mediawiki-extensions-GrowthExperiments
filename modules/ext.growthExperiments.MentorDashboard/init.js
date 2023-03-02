( function () {
	'use strict';
	const Vue = require( 'vue' );
	const Vuex = require( 'vuex' );
	const store = require( './store/index.js' );
	const { convertNumber } = require( '../utils/filters.js' );

	const createApp = ( wrapper, mountPoint ) => {
		const app = Vue.createMwApp( wrapper );
		app.config.globalProperties.$filters = {
			convertNumber
		};
		app.use( store )
			.mount( mountPoint );
	};

	require( './MentorTools/MentorTools.js' );

	Vue.use( Vuex );
	// TODO create an App.vue root component to wrap more Mentor Vue modules as when migrated
	createApp( require( './components/MenteeOverview/MenteeOverview.vue' ), '#vue-root' );
	createApp( require( './components/PersonalizedPraise/PersonalizedPraise.vue' ), '#vue-root-personalizedpraise' );

}() );
