<template>
	<cdx-dialog
		v-model:open="wrappedOpen"
		class="ext-growthExperiments-FilterDialog"
		title="Filter dialog"
		:hide-title="true"
		@update:open="( newVal ) => $emit( 'update:open', newVal )"
	>
		<template #header>
			<div
				class="ext-growthExperiments-FilterDialog__header"
				:class="{ 'ext-growthExperiments-FilterDialog__header--loading': isLoading }">
				<cdx-button
					weight="quiet"
					aria-label="close"
					@click="onCancel"
				>
					<cdx-icon :icon="cdxIconClose" icon-label="next"></cdx-icon>
				</cdx-button>

				<h5 class="ext-growthExperiments-FilterDialog__header__title">
					<slot name="title">
					</slot>
				</h5>
				<cdx-button
					weight="primary"
					action="progressive"
					aria-label="done"
					:disabled="isLoading"
					@click="onSave"
				>
					<slot name="doneBtn">
					</slot>
				</cdx-button>
			</div>
		</template>
		<slot></slot>
		<template #footer>
			<div class="ext-growthExperiments-FilterDialog__footer">
				<span
					class="ext-growthExperiments-FilterDialog__footer__icon"
					:class="
						{ 'ext-growthExperiments-FilterDialog__footer__icon--loading': isLoading }"
				>
					<img
						v-if="isLoading"
						src="../../../images/live-broadcast-anim.svg"
						alt="animated live-broadcast icon"
					>
					<img
						v-else
						src="../../../images/live-broadcast.svg"
						alt="live-broadcast icon"
					>
				</span>
				<slot v-if="isLoading" name="taskCountLoading"></slot>
				<slot v-else name="taskCount"></slot>
			</div>
		</template>
	</cdx-dialog>
</template>

<script>

/**
 * Dialog for filtering tasks in the suggested edits feed
 */

import { ref, toRef } from 'vue';
import { CdxDialog, CdxButton, CdxIcon, useModelWrapper } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';

export default {
	name: 'FilterDialog',
	components: {
		CdxDialog,
		CdxButton,
		CdxIcon
	},
	props: {
		/**
		 * Whether the dialog is visible. Should be provided via a v-model:open
		 * binding in the parent scope.
		 */
		open: {
			type: Boolean,
			default: false
		},
		/**
		 *  When true the dialog has loading styles and the 'progressive' button is disabled
		 */
		isLoading: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'update:open', 'close' ],
	setup( props, { emit } ) {
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const closeSource = ref( undefined );
		const onSave = () => {
			closeSource.value = { closeSource: 'done' };
			wrappedOpen.value = false;
			emit( 'close', closeSource.value );
			closeSource.value = undefined;
		};
		const onCancel = () => {
			closeSource.value = { closeSource: 'cancel' };
			wrappedOpen.value = false;
			emit( 'close', closeSource.value );
			closeSource.value = undefined;
		};
		return {
			cdxIconClose,
			onCancel,
			onSave,
			wrappedOpen
		};
	}
};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
@import './variables.less';
@import './mixins.less';

.ext-growthExperiments-FilterDialog {
	color: @color-base;
	font-size: @font-size-small;
	line-height: @line-height-xx-small;
	/**
	* REVIEW: the following overwrite is set to avoid a duplicated border if
	* the dialog has scrollable content
	*/
	// stylelint-disable-next-line selector-class-pattern
	&.cdx-dialog--dividers {
		// stylelint-disable-next-line selector-class-pattern
		.cdx-dialog__header {
			padding-bottom: 0;
			border-bottom: 0;
		}
		// stylelint-disable-next-line selector-class-pattern
		.cdx-dialog__footer {
			padding-top: 0;
			border-top: 0;
		}
	}

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		border-bottom: @border-subtle;

		&--loading {
			.ext-growthExperiments-loading-stripes-anim();
		}

		&__title {
			font-weight: @font-weight-bold;
			font-size: @font-size-base;
			line-height: @line-height-medium;
		}
	}

	&__footer {
		border-top: @border-subtle;
		padding: @spacing-50 @spacing-100;
		display: flex;
		align-items: center;
		justify-content: flex-start;

		&__icon {
			display: block;
			.ext-growthExperiments-live-broadcast-icon();
			margin-inline-end: @spacing-25;

			&--loading {
				.ext-growthExperiments-live-broadcast-anim();
			}
		}
	}
}
</style>
