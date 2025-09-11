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
	return {
		text: () => message,
	};
};

const i18nPlugin = {
	install: ( app: App ): void => {
		const $i18n = (
			key: string,
			...params: ( string|number )[]
		): FakeMessage => fakeMessage( key, ...params );
		app.provide( 'i18n', { i18n: $i18n } );

		app.config.globalProperties.$i18n = $i18n;
	},
};

export default i18nPlugin;
