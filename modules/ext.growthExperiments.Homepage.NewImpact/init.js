( function () {
	'use strict';
	const Vue = require( 'vue' );
	const { convertNumber } = require( '../utils/filters.js' );

	/**
	 * Setup common configs and helpers for all UserImpact apps.
	 *
	 * @param {string} mountPoint The XPath selector to mount the application.
	 * Must exist in the document before calling this function.
	 * @param {string} layout The layout to use for displaying the app. Can be
	 * one of 'desktop', 'overlay', 'overlay-summary'.
	 * @return {Object} A Vue app instance
	 */
	const createApp = ( mountPoint, layout ) => {
		const wrapper = require( './App.vue' );
		const app = Vue.createMwApp( wrapper, { layout } );
		// $filters property is added to all vue component instances
		app.config.globalProperties.$filters = { convertNumber };
		// provided values can be injected in any component using vue's inject.
		app.provide( 'USER_TO_SHOW_ID', mw.config.get( 'GENewImpactRelevantUserId' ) );
		app.provide( 'USER_TO_SHOW_USERNAME', mw.config.get( 'GENewImpactRelevantUserName' ) );
		app.mount( mountPoint );
		return app;
	};

	if ( mw.config.get( 'homepagemobile' ) ) {
		// We're on mobile, mount the app to show on the summary and the
		// app for the main overlay
		createApp( '#new-impact-vue-root--mobile', 'overlay-summary' );
		createApp( '#new-impact-vue-root', 'overlay' );
	} else {
		// We're on desktop, mount only the app for the desktop homepage module
		createApp( '#new-impact-vue-root', 'desktop' );
	}
}() );
