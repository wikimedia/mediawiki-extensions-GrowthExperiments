import DefaultTheme from 'vitepress/theme';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-banana-i18n';
import CustomLayout from './CustomLayout.vue';
// Import two sets of translations (es,en) for testing purposes
import * as messagesEN from '../../../../../i18n/homepage/en.json';
import * as messagesAR from '../../../../../i18n/homepage/ar.json';
const messages = {
	en: messagesEN.default,
	ar: messagesAR.default
};
const VALID_LOCALES = Object.keys( messages );

/**
 * Decorates the result of vue-banana-i18n plugin calls to "$i18n"
 * to match the the result of MW core's i18n plugin which return a mw.Message
 *
 * @param {Function} vueBananaI18n
 * @return {Function}
 */
const i18nDecorator = ( vueBananaI18n ) => {
	return function ( msg, ...params ) {
		/**
		 * Return an mw.Message partial object with just .text() method
		 *
		 * @typedef {Object} mw.growthDocs.MwMessageInterface
		 *
		 * @property {Function} text parses the given banana message (without html support)
		 */
		return {
			text() {
				return vueBananaI18n( msg, ...params );
			}
		};
	};
};

export default {
	extends: DefaultTheme,
	Layout: CustomLayout,
	enhanceApp( ctx ) {
		const isServer = typeof window === 'undefined';
		// Setup a single Pinia instance for all VitePress pages
		ctx.app.use( createPinia() );

		// VitePress builds the site in nodejs environment when calling npm run docs:build
		if ( !isServer ) {
			// Initialize plugin based on "uselang" query parameter, fallback
			// to english.
			const urlParams = new URLSearchParams( window.location.search );
			const locale = urlParams.get( 'uselang' ) || 'en';

			if ( VALID_LOCALES.indexOf( locale ) === -1 ) {
				// eslint-disable-next-line no-console
				console.error( `Invalid locale ${locale}. Supported locales are: ${VALID_LOCALES.join( ', ' )}` );
			}

			ctx.app.use( createI18n( { messages, locale } ) );

			// HACK wrap vue-banana-i18n results in MW.Message-like objects
			ctx.app.config.globalProperties.$i18n = i18nDecorator(
				ctx.app.config.globalProperties.$i18n
			);
		}

		// Avoid calls to mw.user.getName() by providing a mocked username and gender for all demos.
		ctx.app.provide( 'USER_USERNAME', 'Alice' );
		ctx.app.provide( 'USER_USERGENDER', 'female' );
	}
};
