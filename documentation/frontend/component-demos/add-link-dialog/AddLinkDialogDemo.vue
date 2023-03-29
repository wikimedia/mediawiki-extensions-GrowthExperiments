<template>
	<div>
		<cdx-button @click="open = true">
			Show Add link Dialog
		</cdx-button>

		<!-- eslint-disable vue/no-v-model-argument -->
		<add-link-dialog
			v-model:open="open"
		>
		</add-link-dialog>
	</div>
</template>

<script>
import { CdxButton } from '@wikimedia/codex';
import AddLinkDialog from '../../components/AddLinkDialog.vue';
import { ref } from 'vue';
export default {
	name: 'AddLinkDialogDemo',

	components: {
		AddLinkDialog,
		CdxButton
	},
	setup() {
		const open = ref( false );

		return {
			open

		};

	}
};
</script>
