( function () {
	'use strict';
	const Vue = require( 'vue' );
	const { watch } = require( 'vue' );
	const useMWRestApi = require( './composables/useMWRestApi.js' );
	const { convertNumber } = require( '../utils/filters.js' );
	const { hasIntl } = require( '../utils/Utils.js' );
	const HomepageLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' );
	const logger = require( '../vue-components/plugins/logger.js' );
	const relevantUserId = mw.config.get( 'GEImpactRelevantUserId' );

	/**
	 * Maybe retrieve server exported data for the impact module
	 *
	 * @return {Object} User impact API data server side exported
	 */
	function getServerExportedData() {
		// If there's no special page name assume the module is loaded from a page including
		// {{Special:Impact/username}}
		const specialPageTitle = mw.config.get( 'wgCanonicalSpecialPageName' ) || 'Included';
		const exportedDataConfigKeys = {
			Impact: 'specialimpact',
			Homepage: 'homepagemodules',
			Included: 'specialimpact:included'
		};
		const configKey = exportedDataConfigKeys[ specialPageTitle ];
		return mw.config.get( configKey, {} ).impact;
	}
	/**
	 * Fetch data from growthexperiments/v0/user-impact/<user_id> api endpoint
	 *
	 * @param {number} userId The user id to request data
	 * @return {Promise} A promise that resolves with user impact API data or fails with a
	 * fetch error.
	 */
	const fetchUserImpactData = ( userId ) => new Promise( ( resolve, reject ) => {
		const encodedUserId = encodeURIComponent( `#${ userId }` );
		const query = new URLSearchParams( { lang: mw.config.get( 'wgUserLanguage' ) } );
		const { data, error } = useMWRestApi( `/growthexperiments/v0/user-impact/${ encodedUserId }?${ query.toString() }` );
		watch( [ data, error ], ( [ dataValue, errorValue ] ) => {
			if ( dataValue ) {
				resolve( dataValue );
			}
			if ( errorValue ) {
				reject( errorValue );
			}
		} );
	} );

	/**
	 * Maybe retrieve data from the following sources (in order):
	 *  (1) Server exported data in mw.config values.
	 *  (2) The API response from requesting the user-impact endpoint.
	 *
	 * @param {number} userId The user id to request data
	 * @return {Promise} A promise that resolves with user impact API data or fails with a
	 * fetch error.
	 */
	const getUserImpactData = ( userId ) => {
		const serverSideExportedData = getServerExportedData();
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
	 * one of 'desktop', 'mobile-overlay', 'mobile-summary'.
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
		app.provide( 'RELEVANT_USER_USERNAME', mw.config.get( 'GEImpactRelevantUserName' ) );
		app.provide( 'RELEVANT_USER_MODULE_UNACTIVATED', mw.config.get( 'GEImpactRelevantUserUnactivated' ) );
		app.provide( 'RELEVANT_USER_SUGGESTED_EDITS_ENABLED', mw.config.get( 'GEImpactIsSuggestedEditsEnabledForUser' ) );
		app.provide( 'RELEVANT_USER_SUGGESTED_EDITS_ACTIVATED', mw.config.get( 'GEImpactIsSuggestedEditsActivatedForUser' ) );
		app.provide( 'RENDER_IN_THIRD_PERSON', mw.config.get( 'GEImpactThirdPersonRender' ) );
		app.provide( 'BROWSER_HAS_INTL', hasIntl() );
		app.provide( 'RENDER_MODE', mode );
		app.provide( 'IMPACT_MAX_EDITS', mw.config.get( 'GEImpactMaxEdits' ) );
		app.provide( 'IMPACT_MAX_THANKS', mw.config.get( 'GEImpactMaxThanks' ) );
		app.use( logger, {
			mode,
			logger: new HomepageLogger(
				mw.config.get( 'wgGEHomepagePageviewToken' ),
				mw.config.get( 'wgGEDisableLogging' )
			)
		} );
		app.mount( mountPoint );
		return app;
	};

	const initializeModule = ( data, error ) => {
		const homepageModules = mw.config.get( 'homepagemodules' );
		// Set a default render mode to "desktop" which we'll use for:
		// - desktop display of Special:Homepage
		// - desktop display for Special:Impact
		// - mobile display for Special:Impact
		let renderMode = 'desktop';
		if ( homepageModules && homepageModules.impact ) {
			renderMode = homepageModules.impact.renderMode;
		}
		switch ( renderMode ) {
			case 'mobile-summary':
				// We're on the mobile homepage, mount the app to show on the summary  and the
				// app for the main overlay
				createApp( {
					data,
					error,
					mountPoint: '#impact-vue-root--mobile',
					mode: renderMode
				} );
				createApp( {
					data,
					error,
					mountPoint: '#impact-vue-root',
					mode: 'mobile-overlay'
				} );
				break;
			case 'mobile-details':
				// We're on the mobile details view, mount only one app
				createApp( {
					data,
					error,
					mountPoint: '#impact-vue-root',
					mode: 'mobile-details'
				} );
				break;
			case 'desktop':
				// We're on the desktop view, mount only one app
				createApp( {
					data,
					error,
					mountPoint: '#impact-vue-root',
					mode: renderMode
				} );
				break;
			default:
				// This should not happen, mobile-overlay should not be used from the server,
				// logging unrecgnized modes.
				throw new Error( `Unrecognized homepage module render mode: ${ renderMode }` );
		}
	};

	getUserImpactData( relevantUserId ).then(
		( data ) => initializeModule( data, null ),
		( error ) => initializeModule( null, error )
	);
}() );
