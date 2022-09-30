( function () {
	'use strict';
	const Vue = require( 'vue' );
	const NewImpact = require( './components/NewImpact.vue' );
	Vue.createMwApp( NewImpact )
		.mount( '#new-impact-vue-root' );
}() );
