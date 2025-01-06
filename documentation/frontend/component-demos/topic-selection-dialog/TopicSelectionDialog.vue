<template>
	<cdx-dialog
		class="ext-growthExperiments-TopicSelectionDialog"
		title="Edit another suggestion:"
		close-button-label="Close"
	>
		<div>
			Some content
		</div>
	</cdx-dialog>
</template>

<script>
import { CdxDialog } from '@wikimedia/codex';

export default {
	name: 'TopicSelectionDialog',
	components: {
		CdxDialog
	}
};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

.ext-growthExperiments-TopicSelectionDialog {
	background-color: @background-color-progressive-subtle;
}
</style>
