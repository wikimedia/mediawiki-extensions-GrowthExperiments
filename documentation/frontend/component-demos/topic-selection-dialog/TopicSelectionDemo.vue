<template>
	<div>
		<cdx-button @click="open = true">
			Show topics dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
		<topic-selection-dialog
			v-model:open="open"
			title="Choose some topics you are interested in editing"
			close-button-label="Close"
			@default="open = false"
		>
			Some content
		</topic-selection-dialog>
	</div>
</template>

<script>

import { CdxButton } from '@wikimedia/codex';
import { ref } from 'vue';
import TopicSelectionDialog from './TopicSelectionDialog.vue';

export default {
	name: 'TopicSelectionDemo',
	components: {
		CdxButton,
		TopicSelectionDialog
	},
	setup() {
		const open = ref( false );
		return {
			open
		};
	}

};
</script>
