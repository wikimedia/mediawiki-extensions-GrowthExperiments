<template>
	<div>
		<cdx-button @click="open = true">
			Show Add image Dialog
		</cdx-button>

		<!-- eslint-disable vue/no-v-model-argument -->
		<add-image-dialog
			v-model:open="open"
			v-model:is-checked="isNeverShowAgainChecked"
			@close="onDialogClose"
		>
		</add-image-dialog>
	</div>
</template>

<script>
import { CdxButton } from '@wikimedia/codex';
import AddImageDialog from '../../components/AddImageDialog.vue';
import { ref } from 'vue';
export default {
	name: 'AddImageDialogDemo',

	components: {
		AddImageDialog,
		CdxButton
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
