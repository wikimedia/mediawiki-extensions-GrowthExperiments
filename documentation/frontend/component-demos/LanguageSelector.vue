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

export default {
	name: 'BasicSelect',
	components: { CdxSelect },
	setup() {
		const banana = useI18n();
		const selection = ref( banana.locale );

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
