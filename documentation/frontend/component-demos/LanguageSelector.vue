<template>
	<cdx-select
		v-model:selected="selection"
		:menu-items="menuItems"
		default-label="Choose a language"
		@update:selected="onLanguageUpdate"
	></cdx-select>
</template>

<script>
import { ref } from 'vue';
import { useI18n } from 'vue-banana-i18n';
import { CdxSelect } from '@wikimedia/codex';

const MENU_ITEMS = [
	{ label: 'English', value: 'en' },
	{ label: 'Arabic', value: 'ar' }
];

const isServer = typeof window === 'undefined';

export default {
	name: 'LanguageSelector',
	components: { CdxSelect },
	setup() {
		const selection = ref( null );
		let banana = { locale: 'en' };
		if ( !isServer ) {
			banana = useI18n();
			selection.value = banana.locale;
		}

		function onLanguageUpdate( newVal ) {
			banana.setLocale( newVal );
			selection.value = newVal;
			const url = new URL( window.location.href );
			url.searchParams.set( 'uselang', newVal );
			history.replaceState( null, null, url );
		}
		return {
			onLanguageUpdate,
			selection,
			menuItems: MENU_ITEMS
		};
	}
};
</script>
