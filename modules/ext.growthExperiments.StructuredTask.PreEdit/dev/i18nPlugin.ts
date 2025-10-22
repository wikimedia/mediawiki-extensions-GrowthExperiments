import messages from '../../../i18n/homepage/en.json';
// the import below requires eslint-plugin-n with version v17.11.0 and `"ignoreTypeImport": true,` config
// eslint-disable-next-line n/no-missing-import
import MediaWiki from '@wikimedia/types-wikimedia';
import { App } from 'vue';

type FakeMessage = Partial<ReturnType<MediaWiki['message']>>;
const fakeMessage = ( key: string, ...params: ( string|number )[] ): FakeMessage => {
	let message: string = ( messages as unknown as Record<string, string> )[ key ];
	let index = 0;
	for ( const param of params ) {
		index++;
		// @ts-expect-error incorrect type definition for String.prototype.replace ?
		message = message.replace( `$${ index }`, param );
	}
	const text = (): string => message || ( 'i18n-not-found:' + key );
	return {
		text,
		parse: text,
	};
};

const i18nPlugin = {
	install: ( app: App ): void => {
		const $i18n = (
			key: string,
			...params: ( string|number )[]
		): FakeMessage => fakeMessage( key, ...params );
		app.provide( 'i18n', $i18n );

		app.config.globalProperties.$i18n = $i18n;

		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		function renderI18nHtml( el: HTMLElement, binding: any ): void {

			let message;

			if ( Array.isArray( binding.value ) ) {
				if ( binding.arg === undefined ) {
					// v-i18n-html="[ ...params ]" (error)
					throw new Error( 'v-i18n-html used with parameter array but without message key' );
				}
				// v-i18n-html:messageKey="[ ...params ]"
				message = fakeMessage( binding.arg, binding.value );
			} else if ( binding.value instanceof fakeMessage ) {
				// v-i18n-html="mw.message( '...' ).params( [ ... ] )"
				message = binding.value;
			} else {
				// v-i18n-html:foo or v-i18n-html="'foo'"
				message = fakeMessage( binding.arg || binding.value );
			}

			el.innerHTML = message.parse();
		}

		app.directive( 'i18n-html', {
			mounted: renderI18nHtml,
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			updated( el: HTMLElement, binding: any ) {
				// This function is invoked often, every time anything in the component changes.
				// We don't want to rerender unnecessarily, because that's wasteful and can cause
				// strange issues like T327229. For each possible type of binding.value, compare it
				// to binding.oldValue, and abort if they're equal. This does not account for
				// changes in binding.arg; we can't detect those, so there's a warning in the
				// documentation above explaining that using a dynamic argument is not supported.

				// eslint-disable-next-line @typescript-eslint/no-explicit-any
				const areArraysEqual = ( arr1: any[], arr2: any[] ): boolean => Array.isArray( arr1 ) && Array.isArray( arr2 ) &&
					arr1.length === arr2.length &&
					arr1.every( ( val, index ) => arr2[ index ] === val );
				const areMessagesEqual = ( msg1: object, msg2: object ): boolean => msg1 instanceof fakeMessage && msg2 instanceof fakeMessage &&
					// @ts-expect-error key and parameters are not exposed by MediaWiki['message']
					msg1.key === msg2.key && areArraysEqual( msg1.parameters, msg2.parameters );

				if (
					binding.value === binding.oldValue ||
					areArraysEqual( binding.value, binding.oldValue ) ||
					areMessagesEqual( binding.value, binding.oldValue )
				) {
					return;
				}

				renderI18nHtml( el, binding );
			},
		} );
	},
};

export default i18nPlugin;
