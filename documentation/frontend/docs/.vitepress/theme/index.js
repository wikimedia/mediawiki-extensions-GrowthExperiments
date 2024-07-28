import DefaultTheme from 'vitepress/theme';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-banana-i18n';
import CustomLayout from './CustomLayout.vue';
import { DEFAULT_LOCALE, messages } from '../i18n.js';

/**
 * Decorates the result of vue-banana-i18n plugin calls to "$i18n"
 * to match the the result of MW core's i18n plugin which return a mw.Message
 *
 * @param {Function} vueBananaI18n
 * @return {Function}
 */
const i18nDecorator = ( vueBananaI18n ) => function ( msg, ...params ) {
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

export default {
	extends: DefaultTheme,
	Layout: CustomLayout,
	enhanceApp( ctx ) {
		// Setup a single Pinia instance for all VitePress pages
		ctx.app.use( createPinia() );

		ctx.app.use( createI18n( { messages, locale: DEFAULT_LOCALE } ) );

		// HACK wrap vue-banana-i18n results in MW.Message-like objects
		ctx.app.config.globalProperties.$i18n = i18nDecorator(
			ctx.app.config.globalProperties.$i18n
		);

		// Avoid calls to mw.user.getName() by providing a mocked username and gender for all demos.
		ctx.app.provide( 'USER_USERNAME', 'Alice' );
		ctx.app.provide( 'USER_USERGENDER', 'female' );
	}
};
