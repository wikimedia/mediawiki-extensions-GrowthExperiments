( function () {
	'use strict';

	const reviseToneOnboardingPrefName = 'growthexperiments-revisetone-onboarding',
		useExperiment = require( '../../ext.growthExperiments.StructuredTask/revisetone/useExperiment.js' );

	/**
	 * Launch the Revise Tone onboarding quiz dialog.
	 *
	 * This function can be called from anywhere (e.g., Help Panel) to show the quiz.
	 * It creates a Vue app instance and mounts it to a temporary DOM element.
	 *
	 * @param {Object} [options]
	 * @param {boolean} [options.setSeenPreference=true] Whether to mark the quiz as seen
	 *   when dismissed. Set to false when launching from Help Panel (retake scenario).
	 * @return {Object} The Vue app instance
	 */
	function launchReviseToneQuiz( options = {} ) {
		const setSeenPreference = options.setSeenPreference !== false;

		try {
			const wrapper = require( '../App.vue' );

			// eslint-disable-next-line no-undef
			const app = Vue.createMwApp( wrapper, {
				prefName: setSeenPreference ? reviseToneOnboardingPrefName : null,
			} );

			app.provide( 'mw.language', mw.language );
			app.provide( 'mw.Api', mw.Api );
			app.provide( 'mw.user', mw.user );
			app.provide( 'mw.hook', mw.hook );
			app.provide( 'mw.track', mw.track );
			app.provide( 'experiment', useExperiment() );

			// Create or reuse mount point
			let mountPoint = document.querySelector( '.growth-experiments-structuredtask-preedit-vue-app' );
			if ( !mountPoint ) {
				mountPoint = document.createElement( 'div' );
				mountPoint.classList.add( 'growth-experiments-structuredtask-preedit-vue-app' );
				document.body.appendChild( mountPoint );
			} else {
				// If mount point exists and has a mounted app, unmount it first
				// This handles the case where the quiz is launched multiple times
				// eslint-disable-next-line no-underscore-dangle
				if ( mountPoint.__vue_app__ ) {
					// eslint-disable-next-line no-underscore-dangle
					mountPoint.__vue_app__.unmount();
				}
			}

			app.mount( mountPoint );
			return app;
		} catch ( error ) {
			mw.log.error( 'Error launching Revise Tone quiz:', error );
			throw error;
		}
	}

	module.exports = {
		launchReviseToneQuiz: launchReviseToneQuiz,
	};

}() );
