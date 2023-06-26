( function () {
	'use strict';
	const Vue = require( 'vue' );
	const store = require( './store/index.js' );
	const { convertNumber } = require( '../utils/filters.js' );
	const MentorDashboardLogger = require( './logger/Logger.js' );
	const loggerPlugin = require( './plugins/logger.js' );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $dashboard = $( '.growthexperiments-mentor-dashboard-container' );

	const handleClick = ( e ) => {
		const pageviewToken = mw.config.get( 'wgGEMentorDashboardPageviewToken' ),
			logger = new MentorDashboardLogger( pageviewToken ),
			$link = $( e.target ),
			$module = $link.closest( '.growthexperiments-mentor-dashboard-module' ),
			linkId = $link.data( 'link-id' ),
			linkData = $link.data( 'link-data' ),
			moduleName = $module.data( 'module-name' ),
			extraData = { linkId: linkId };

		if ( linkData !== undefined && linkData !== null ) {
			extraData.linkData = linkData;
		}

		logger.log( moduleName, 'link-click', extraData );
	};
	$dashboard
		.on( 'click', 'a[data-link-id]', handleClick );

	const createApp = ( wrapper, mountPoint, module ) => {
		const app = Vue.createMwApp( wrapper );
		app.config.globalProperties.$filters = {
			convertNumber
		};
		// Mentor dashboard has no official mobile support (pending T279965)
		app.provide( 'RENDER_MODE', 'desktop' );
		app.use( store )
			.use( loggerPlugin, {
				module: module,
				pageviewToken: mw.config.get( 'wgGEMentorDashboardPageviewToken' )
			} )
			.mount( mountPoint );
	};

	require( './MentorTools/MentorTools.js' );

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
