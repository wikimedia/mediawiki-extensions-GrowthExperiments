<template>
	<!-- necessary to make LTR/RTL demos work -->
	<div>
		<layout>
			<!-- Show the language selector in all demo pages -->
			<template v-if="page.relativePath !== 'index.md'" #nav-bar-content-before>
				<language-selector></language-selector>
			</template>
			<!-- this is where markdown content will be rendered -->
			<!-- eslint-disable-next-line vue/no-undef-components -->
			<Content></Content>
		</layout>
	</div>
</template>

<script setup>
import { provide, ref } from 'vue';
import { useData } from 'vitepress';
import { useI18n } from 'vue-banana-i18n';
import DefaultTheme from 'vitepress/theme';
import { DEFAULT_LOCALE, VALID_LOCALES, LOCALE_READING_DIRECTION } from '../i18n.js';
import { name } from '../../../package.json';
import LanguageSelector from '../../../component-demos/LanguageSelector.vue';
const { Layout } = DefaultTheme;
const { page } = useData();
const isServer = typeof window === 'undefined';
// We need to call vue-banana-i18n's useIi18n() from a custom component
// so Vue finds the registered plugin once an application instance exists.
const banana = useI18n();
banana.registerParserPlugin( 'sitename', () => name );
// Initialize a ref to the default locale reading direction
const readingDirection = ref( LOCALE_READING_DIRECTION[ DEFAULT_LOCALE ] );
const setReadingDirection = ( newVal ) => {
	readingDirection.value = newVal;
};
const setDemosLocale = ( newLocale ) => {
	banana.setLocale( newLocale );
	// Update the reading direction according to the set newLocale
	setReadingDirection( LOCALE_READING_DIRECTION[ newLocale ] );
};

// Run client-only code using window object
if ( !isServer ) {
	const urlParams = new URLSearchParams( window.location.search );
	const locale = urlParams.get( 'uselang' ) || DEFAULT_LOCALE;

	if ( !VALID_LOCALES.includes( locale ) ) {
		// eslint-disable-next-line no-console
		console.error( `Invalid locale ${ locale }. Supported locales are: ${ VALID_LOCALES.join( ', ' ) }` );
	}

	// Set banana's locale (priorly initialized to i18n.js#DEFAULT_LOCALE in theme/index.js )
	// to the value retrieved from the query parameter ?uselang and validated.
	setDemosLocale( locale );
	// Make the readingDirection widely available, used in DemoWrapper to switch directions
	provide( 'READING_DIRECTION', readingDirection );
	// Convenience method to update the locale and reading direction consistently. eg: used
	// from LanguageSelector.vue
	provide( 'setDemosLocale', setDemosLocale );
}

</script>
