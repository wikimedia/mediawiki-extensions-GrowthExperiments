( function () {
	'use strict';
	const Vue = require( 'vue' );
	const Vuex = require( 'vuex' );
	const store = require( './store/index.js' );
	const clickOutside = require( './directives/click-outside.directive.js' );

	// TODO create an App.vue root component to wrap more Mentor modules as they appear
	const MenteeOverview = require( './components/MenteeOverview/MenteeOverview.vue' );

	Vue.use( Vuex );

	Vue.createMwApp( MenteeOverview )
		.directive( 'click-outside', clickOutside )
		.use( store )
		.mount( '#vue-root' );
}() );
