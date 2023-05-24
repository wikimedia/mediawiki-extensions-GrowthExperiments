( function () {
	'use strict';
	const Vue = require( 'vue' );
	const Vuex = require( 'vuex' );
	const store = require( './store/index.js' );
	const { convertNumber } = require( '../utils/filters.js' );
	const logger = require( './plugins/logger.js' );

	const createApp = ( wrapper, mountPoint, module ) => {
		const app = Vue.createMwApp( wrapper );
		app.config.globalProperties.$filters = {
			convertNumber
		};
		app.use( store )
			.use( logger, {
				module: module,
				pageviewToken: mw.config.get( 'wgGEMentorDashboardPageviewToken' )
			} )
			.mount( mountPoint );
	};

	require( './MentorTools/MentorTools.js' );

	Vue.use( Vuex );
	// TODO create an App.vue root component to wrap more Mentor Vue modules as when migrated
	createApp(
		require( './components/MenteeOverview/MenteeOverview.vue' ),
		'#vue-root',
		'mentee-overview'
	);
	createApp(
		require( './components/PersonalizedPraise/PersonalizedPraise.vue' ),
		'#vue-root-personalizedpraise',
		'personalized-praise'
	);

}() );
