( function () {
	'use strict';
	const Vue = require( 'vue' );
	const { watch } = require( 'vue' );
	const useMWRestApi = require( './composables/useMWRestApi.js' );
	const { convertNumber } = require( '../utils/filters.js' );
	const { hasIntl } = require( '../../utils/Utils.js' );
	const logger = require( './plugins/logger.js' );
	const relevantUserId = mw.config.get( 'GENewImpactRelevantUserId' );

	/**
	 * Fetch data from growthexperiments/v0/user-impact/<user_id> api endpoint
	 *
	 * @param {number} userId The user id to request data
	 * @return {Promise} A promise that resolves with user impact API data or fails with a
	 * fetch error.
	 */
	const fetchUserImpactData = ( userId ) => {
		// eslint-disable-next-line compat/compat
		return new Promise( ( resolve, reject ) => {
			const encodedUserId = encodeURIComponent( `#${userId}` );
			const { data, error } = useMWRestApi( `/growthexperiments/v0/user-impact/${encodedUserId}` );
			watch( [ data, error ], ( [ dataValue, errorValue ] ) => {
				if ( dataValue ) {
					resolve( dataValue );
				}
				if ( errorValue ) {
					reject( errorValue );
				}
			} );
		} );
	};

	/**
	 * Maybe retrieve data from the following sources (in order):
	 *  (1) Server exported data in specialimpact/homepagemodules mw.config values.
	 *  (2) The API response from requesting the user-impact endpoint.
	 *
	 * @param {number} userId The user id to request data
	 * @return {Promise} A promise that resolves with user impact API data or fails with a
	 * fetch error.
	 */
	const getUserImpactData = ( userId ) => {
		const specialPageTitle = mw.config.get( 'wgCanonicalSpecialPageName' );
		const exportedDataConfigKeys = {
			Impact: 'specialimpact',
			Homepage: 'homepagemodules'
		};
		const configKey = exportedDataConfigKeys[ specialPageTitle ];
		const serverSideExportedData = mw.config.get( configKey, {} ).impact;
		// eslint-disable-next-line compat/compat
		return new Promise( ( resolve, reject ) => {
			if ( serverSideExportedData && serverSideExportedData.impact ) {
				resolve( serverSideExportedData.impact );
			} else {
				fetchUserImpactData( userId ).then( resolve, reject );
			}
		} );
	};

	/**
	 * Setup common configs and helpers for all UserImpact apps.
	 *
	 * @param {Object} appConfig Impact Vue application condiff
	 * @param {string} appConfig.mountPoint The XPath selector to mount the application.
	 * Must exist in the document before calling this function.
	 * @param {string} appConfig.mode The render mode to use for displaying the app. Can be
	 * one of 'desktop', 'overlay', 'overlay-summary'.
	 * @param {Object} appConfig.data The initial data to boot the application with.
	 * @param {number} appConfig.error Eventual fetch error from requesting data from API.
	 * @return {Object} A Vue app instance
	 */
	const createApp = ( { mountPoint, mode, data, error } ) => {
		const wrapper = require( './App.vue' );
		const app = Vue.createMwApp( wrapper );
		// $filters property is added to all vue component instances
		app.config.globalProperties.$filters = { convertNumber };
		// provided values can be injected in any component using vue's inject.
		app.provide( 'RELEVANT_USER_ID', relevantUserId );
		app.provide( 'RELEVANT_USER_DATA', data );
		app.provide( 'FETCH_ERROR', error );
		app.provide( 'RELEVANT_USER_USERNAME', mw.config.get( 'GENewImpactRelevantUserName' ) );
		app.provide( 'RELEVANT_USER_MODULE_UNACTIVATED', mw.config.get( 'GENewImpactRelevantUserUnactivated' ) );
		app.provide( 'RELEVANT_USER_SUGGESTED_EDITS_ENABLED', mw.config.get( 'GENewImpactIsSuggestedEditsEnabledForUser' ) );
		app.provide( 'RELEVANT_USER_SUGGESTED_EDITS_ACTIVATED', mw.config.get( 'GENewImpactIsSuggestedEditsActivatedForUser' ) );
		app.provide( 'RENDER_IN_THIRD_PERSON', mw.config.get( 'GENewImpactThirdPersonRender' ) );
		app.provide( 'BROWSER_HAS_INTL', hasIntl() );
		app.provide( 'RENDER_MODE', mode );
		app.use( logger, {
			mode,
			enabled: mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			pageviewToken: mw.config.get( 'wgGEHomepagePageviewToken' )
		} );
		app.mount( mountPoint );
		return app;
	};

	const initializeModule = ( data, error ) => {
		if ( mw.config.get( 'homepagemobile' ) ) {
			// We're on the mobile homepage, mount the app to show on the summary  and the
			// app for the main overlay
			createApp( {
				data,
				error,
				mountPoint: '#new-impact-vue-root--mobile',
				mode: 'overlay-summary'
			} );
			createApp( {
				data,
				error,
				mountPoint: '#new-impact-vue-root',
				mode: 'overlay'
			} );
		} else {
			// We're on the mobile homepage or Special:Impact, mount only the app
			// for the desktop homepage module
			createApp( {
				data,
				error,
				mountPoint: '#new-impact-vue-root',
				mode: 'desktop'
			} );
		}
	};

	getUserImpactData( relevantUserId ).then(
		( data ) => initializeModule( data, null ),
		( error ) => initializeModule( null, error )
	);
}() );
