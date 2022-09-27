( function () {
	'use strict';
	const Vue = require( 'vue' );
	const NewImpact = require( './components/NewImpact.vue' );
	const { convertNumber } = require( '../utils/filters.js' );
	const app = Vue.createMwApp( NewImpact );

	app.mount( '#new-impact-vue-root' );
	app.config.globalProperties.$filters = { convertNumber };

	if ( mw.config.get( 'homepagemobile' ) ) {
		const NewImpactSummary = require( './components/NewImpactSummary.vue' );
		const mobileApp = Vue.createMwApp( NewImpactSummary );
		mobileApp.config.globalProperties.$filters = { convertNumber };
		mobileApp.mount( '#new-impact-vue-root--mobile' );
	}
}() );
