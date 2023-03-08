<template>
	<!-- eslint-disable vue/no-v-model-argument -->
	<!-- The provided header from Codex Dialog is hidden in order to
		provide a custom header with the title and a button -->
	<cdx-dialog
		v-model:open="open"
		class="ext-growthExperiments-OnboardingDialog"
		title="Onboarding Dialog"
		:hide-title="true"

		@update:open="{ $emit( 'update:open', false ); currentIndex = initialStep }"
	>
		<!-- Custom Dialog Header -->
		<div class="ext-growthExperiments-OnboardingDialog__header">
			<!-- Slot for dialog title -->
			<h5 class="ext-growthExperiments-OnboardingDialog__header__title">
				<slot name="title"></slot>
			</h5>
			<!-- Header button that will display in all steps except last one
				if slot with inner text is provided  -->
			<div>
				<cdx-button
					v-if="$slots.headerbtntext && currentIndex !== totalSteps - 1"

					weight="quiet"

					@click="$emit( 'update:open', false ); currentIndex = initialStep"
				>
					<slot name="headerbtntext" :current-index="currentIndex"></slot>
				</cdx-button>
			</div>
		</div>

		<!-- Dialog Content -->
		<div class="ext-growthExperiments-OnboardingDialog__content">
			<!-- Dialog paginator indicate current steps and total steps  -->
			<div class="ext-growthExperiments-OnboardingDialog__content__fixed-content">
				<onboarding-paginator
					:total-steps="totalSteps"
					:current-step="currentIndex + 1 "
				>
				</onboarding-paginator>
			</div>
			<div class="ext-growthExperiments-OnboardingDialog__content__body">
				<!-- This slot contains the content for the different steps in the dialog.
					The content for each step must be wrapped into a OnboardingStep component  -->
				<slot name="body" :current-index="currentIndex">
				</slot>
			</div>
		</div>
		<!-- Dialog Footer -->
		<div class="ext-growthExperiments-OnboardingDialog__footer">
			<div
				class="ext-growthExperiments-OnboardingDialog__footer__actions"
			>
				<!-- Footer Actions Prev -->

				<!-- The first step of the dialog displays a checkbox
					if text content for the slot label is provided.
					The checkbox value can be accesed by v-model:checkboxValue -->
				<div
					v-if="currentIndex === 0"
					class="ext-growthExperiments-OnboardingDialog__footer__actions-prev">
					<!-- eslint-disable-next-line max-len -->
					<cdx-checkbox
						v-if="$slots.checkbox"
						v-model="modelValue"
						:value="modelValue"
						@update:model-value="$emit( 'update:modelValue', modelValue )"
					>
						<slot name="checkbox">
						</slot>
					</cdx-checkbox>
				</div>
				<!-- All the following steps display an Icon only button to navigate
					to the previous step -->
				<div
					v-else
					class="ext-growthExperiments-OnboardingDialog__footer__actions-prev">
					<cdx-button
						aria-label="previous"
						@click="onPrevClick">
						<cdx-icon :icon="cdxIconPrevious" icon-label="previous"></cdx-icon>
					</cdx-button>
				</div>
				<!-- Footer Acctions Next -->
				<!-- The last step displays a button with a slot for the text content -->
				<div
					v-if="currentIndex + 1 === totalSteps"
					class="ext-growthExperiments-OnboardingDialog__footer__actions-next">
					<cdx-button
						weight="primary"

						action="progressive"
						@click="$emit( 'update:open', false ); currentIndex = initialStep">
						<slot name="last-step-button-text"></slot>
					</cdx-button>
				</div>
				<!-- All the previous steps display an Icon only button to navigate
					to the next step -->
				<div
					v-else
					class="ext-growthExperiments-OnboardingDialog__footer__actions-next">
					<cdx-button
						weight="primary"
						action="progressive"
						class="cdx-button--icon-only"
						aria-label="next"
						@click="onNextClick">
						<cdx-icon :icon="cdxIconNext" icon-label="next"></cdx-icon>
					</cdx-button>
				</div>
			</div>
		</div>
	</cdx-dialog>
</template>

<script>
import { CdxDialog, CdxButton, CdxIcon, CdxCheckbox } from '@wikimedia/codex';
import { cdxIconNext, cdxIconPrevious } from '@wikimedia/codex-icons';
import OnboardingPaginator from './OnboardingPaginator.vue';

import { ref } from 'vue';

export default {
	name: 'OnboardingDialog',

	components: {
		CdxDialog,
		CdxButton,
		CdxIcon,
		CdxCheckbox,
		OnboardingPaginator

	},
	props: {

		/**
		 * The total number of steps the dialog displays
		 */
		totalSteps: {
			type: Number,
			default: 0

		},

		/**
		 * First step to show when the dialog open
		 */
		initialStep: {
			type: Number,
			default: 0
		}

	},
	emits: [ 'update:open', 'update:modelValue' ],

	setup( props, { modelValue } ) {

		const currentIndex = ref( props.initialStep );

		const onNextClick = () => {
			if ( currentIndex.value + 1 < props.totalSteps ) {

				currentIndex.value++;
			}
		};
		const onPrevClick = () => {
			if ( currentIndex.value > 0 ) {
				currentIndex.value--;
			}
		};

		return {

			currentIndex,
			onNextClick,
			onPrevClick,
			cdxIconNext,
			cdxIconPrevious,
			open,
			modelValue

		};
	}
};
</script>

<style lang="less">
	@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
	@import './variables.less';

	.ext-growthExperiments-OnboardingDialog {
		// Overwrite Codex's vertical gutter on SimpleDialog
		// stylelint-disable-next-line selector-class-pattern
		> .cdx-dialog {
			height: 520px;
			padding-top: 0;
			padding-bottom: 0;
			color: @color-base;
			// Overwrite Codex's horizontal padding on SimpleDialog
			// stylelint-disable-next-line selector-class-pattern
			.cdx-dialog__body {
				padding-left: 0;
				padding-right: 0;
				margin-top: 0;
				margin-bottom: 0;
			}
			// Overwrite the Codex Dialog size on small screens
			// to get fullscreen widht and height on mobile
			@media ( max-width: 499px ) {
				width: 100%;
				height: 100%;
				border: 0;
				box-shadow: none;
				position: absolute;
				top: 0;

				.ext-growthExperiments-OnboardingDialog__footer {
					position: fixed;
					bottom: 0;
					width: 100%;
				}
			}

			.ext-growthExperiments-OnboardingDialog {
				&__header {
					display: flex;
					justify-content: space-between;
					// This is the background color for the AddlinkDialog Images
					// and should be replaced as discussed on
					// https://phabricator.wikimedia.org/T332567
					// with a DS background color
					background-color: @onboardingBackgroundColor;

					&__title {
						font-size: @font-size-medium;
						line-height: 2.72em;
						font-weight: @font-weight-bold;
						min-height: @size-275;
						padding-left: @spacing-75;
						padding-right: @spacing-75;
					}
				}

				&__content {
					&__fixed-content {
						padding-left: @spacing-75;
						padding-right: @spacing-75;
						position: absolute;
						z-index: 1;
					}
				}

				&__footer {
					border-top: @border-width-base @border-style-base @border-color-base;
					padding: @spacing-75 @spacing-100;

					&__actions {
						display: flex;
						align-items: center;
						justify-content: space-between;
						font-size: @font-size-small;
					}
				}
			}
		}
	}
</style>
