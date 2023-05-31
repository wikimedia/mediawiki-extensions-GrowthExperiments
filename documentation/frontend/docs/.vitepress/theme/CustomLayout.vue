<template>
	<Layout>
		<!-- Show the language selector in all demo pages -->
		<template v-if="page.relativePath !== 'index.md'" #nav-bar-content-before>
			<LanguageSelector></LanguageSelector>
		</template>
		<!-- this is where markdown content will be rendered -->
		<!-- eslint-disable-next-line vue/no-undef-components -->
		<Content></Content>
	</Layout>
</template>

<script setup>
import { useData } from 'vitepress';
import { useI18n } from 'vue-banana-i18n';
import DefaultTheme from 'vitepress/theme';
import { name } from '../../../package.json';
import LanguageSelector from '../../../component-demos/LanguageSelector.vue';
const { Layout } = DefaultTheme;
const { page } = useData();
const isServer = typeof window === 'undefined';
// vue-banana-i18n is initialized only in browser environment,
// skip registering plugins .
if ( !isServer ) {
	// We need to call vue-banana-i18n's useIi18n() from a custom component
	// so Vue finds the registered plugin once an application instance exists.
	const banana = useI18n();
	banana.registerParserPlugin( 'sitename', () => {
		return name;
	} );
}

</script>
