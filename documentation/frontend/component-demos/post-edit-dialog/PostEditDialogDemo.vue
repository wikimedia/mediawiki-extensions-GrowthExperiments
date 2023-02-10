<template>
	<div class="ext-growthExperiments-PostEditDialog">
		<cdx-button @click="open = true">
			Show post edit dialog
		</cdx-button>
		<!-- eslint-disable vue/no-v-model-argument -->
		<cdx-dialog
			v-model:open="open"
			class="postedit"
			title="Edit another suggestion:"
			close-button-label="Close"
			@default="open = false"
		>
			<div class="ext-growthExperiments-PostEditDialog__content">
				<cdx-card :thumbnail="thumbnailData">
					<template #title>
						Classical kemençe
					</template>
					<template #description>
						stringed instrument played with a bow, from Asia Minor
					</template>
				</cdx-card>
				<div class="mw-ge-help-panel-postedit-navigation">
					<div class="pager">
						<strong>1</strong> of <strong>5</strong> suggestions
					</div>
					<cdx-button aria-label="Back">
						<cdx-icon :icon="cdxIconPrevious"></cdx-icon>
					</cdx-button>
					<cdx-button aria-label="Forward">
						<cdx-icon :icon="cdxIconNext"></cdx-icon>
					</cdx-button>
				</div>
				<cdx-button>View all suggested edits</cdx-button>
			</div>
		</cdx-dialog>
	</div>
</template>

<script>

import { CdxButton, CdxCard, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { ref } from 'vue';
import { cdxIconPrevious, cdxIconNext } from '@wikimedia/codex-icons';

const thumbnailData = {
	width: 200,
	url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e0/Kemence_Sezgin_(1).jpg/200px-Kemence_Sezgin_(1).jpg'
};

export default {
	name: 'PostEditDialogDemo',
	components: { CdxIcon, CdxCard, CdxDialog, CdxButton },
	setup() {
		const open = ref( false );
		return {
			open,
			cdxIconNext,
			cdxIconPrevious,
			thumbnailData
		};
	}

};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';

.ext-growthExperiments-PostEditDialog {
	&__content {
		background-color: @background-color-progressive-subtle;
	}
}
</style>
