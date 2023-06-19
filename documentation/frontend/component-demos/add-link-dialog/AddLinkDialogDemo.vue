<template>
	<demo-wrapper>
		<cdx-button @click="open = true">
			Show Add link Dialog
		</cdx-button>

		<!-- eslint-disable vue/no-v-model-argument -->
		<add-link-dialog
			v-model:open="open"
			v-model:is-checked="isNeverShowAgainChecked"
			learn-more-link="https://www.wikipedia.org/"
			@close="onDialogClose"
		>
		</add-link-dialog>
	</demo-wrapper>
</template>

<script>
import { CdxButton } from '@wikimedia/codex';
import AddLinkDialog from '../../components/AddLinkDialog.vue';
import DemoWrapper from '../DemoWrapper.vue';
import { ref } from 'vue';
export default {
	name: 'AddLinkDialogDemo',

	components: {
		AddLinkDialog,
		CdxButton,
		DemoWrapper
	},
	setup() {
		const open = ref( false );
		const isNeverShowAgainChecked = ref( false );

		function onDialogClose( result ) {
			// eslint-disable-next-line no-console
			console.log( 'onDialogClose', result );
		}

		return {
			open,
			isNeverShowAgainChecked,
			onDialogClose
		};

	}
};
</script>
