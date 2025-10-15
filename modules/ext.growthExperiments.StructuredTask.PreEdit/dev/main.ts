import { App, Component, createApp } from 'vue';
import '@wikimedia/codex/dist/codex.style.css';
import '@wikimedia/codex-design-tokens/theme-wikimedia-ui-root.css';
import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.css';
import DEMOS from './demos/index';
import i18nPlugin from './i18nPlugin';
import loggerPlugin from '../../vue-components/plugins/logger';
// TODO find ways to reuse this between app and tests
const mwLanguageMock = {
	convertNumber: ( x: number ) => String( x ),
	getFallbackLanguageChain: () => ( [ 'en' ] ),
};
const appSelect = document.querySelector( '.app-selector' );
let currentApp:( App|null ) = null;

const bootstrap = (): void => {
	const createDemoApp = ( app: Component ): App<Element> => {
		const devApp = createApp( app, {
			taskType: 'revise-tone',
		} );

		devApp.use( loggerPlugin, {
			mode: 'dev',
			logger: console,
		} );
		devApp.use( i18nPlugin );

		devApp.provide( 'mw.language', mwLanguageMock );

		devApp.mount( '#app' );

		return devApp;
	};

	// The selector is always present, just make TS happy
	if ( appSelect ) {
		Object.keys( DEMOS ).forEach( ( demoName ) => {
			const option = document.createElement( 'option' );
			option.value = demoName;
			option.text = demoName;
			appSelect.appendChild( option );
		} );

		const urlParams = new URLSearchParams( window.location.search );
		let appParam = urlParams.get( 'app' );
		appParam = appParam || 'Main App';
		const selectedOption: ( HTMLOptionElement|null ) = document.querySelector(
			`option[value="${ appParam }"]`,
		);
		// Set the selected option in the selector if an app query param is informed, to let HMR act on the same app
		// and keeping selection after reload
		if ( selectedOption ) {
			selectedOption.selected = true;
			if ( appParam && appParam in DEMOS ) {
				// @ts-expect-error not sure how to access DEMOS with a string as a key and ts not complain
				currentApp = createDemoApp( DEMOS[ appParam ] );
			}
		}
		appSelect.addEventListener( 'change', ( event: Event ) => {
			// Unmount any prior mounted app
			if ( currentApp ) {
				currentApp.unmount();
			}
			if ( event.target instanceof HTMLSelectElement ) {
				location.search = `app=${ encodeURIComponent( event.target.value ) }`;
				if ( event.target.value in DEMOS ) {
					// @ts-expect-error not sure how to access DEMOS with a string as a key and ts not complain
					currentApp = createDemoApp( DEMOS[ event.target.value ] );
				}
			}
		} );
	}
};

bootstrap();
