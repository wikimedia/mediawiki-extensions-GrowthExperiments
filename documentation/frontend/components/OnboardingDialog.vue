<template>
	<!-- eslint-disable vue/no-v-model-argument -->
	<cdx-dialog
		v-model:open="wrappedOpen"
		class="ext-growthExperiments-OnboardingDialog"
		title="Introduction"
		:hide-title="true"
		@update:open="( newVal ) => onDialogOpenUpdate( newVal )"
	>
		<template #header>
			<div
				class="ext-growthExperiments-OnboardingDialog__header"
			>
				<!-- Slot for dialog title -->
				<h4 class="ext-growthExperiments-OnboardingDialog__header__title">
					<slot name="title"></slot>
				</h4>
				<!-- Header button that will display in all steps except last one
				if slot with inner text is provided  -->
				<div class="ext-growthExperiments-OnboardingDialog__header__button">
					<cdx-button
						v-if="$slots.headerbtntext && currentStep !== totalSteps "
						weight="quiet"
						@click="onHeaderBtnClick"
					>
						<slot name="headerbtntext"></slot>
					</cdx-button>
				</div>
			</div>
		</template>

		<!-- Dialog Content -->
		<div class="ext-growthExperiments-OnboardingDialog__content">
			<!-- Dialog paginator indicate current steps and total steps. -->
			<onboarding-paginator
				v-if="showPaginator && totalSteps > 1 && hasSteps"
				class="ext-growthExperiments-OnboardingDialog__content__paginator"
				:total-steps="totalSteps"
				:current-step="currentStep"
			></onboarding-paginator>

			<!-- Slot for the dialog steps.
				Content for each step can be provided by using the named slots:
				#step1, #step2, #step3, etc..
				-->
			<slot v-if="hasSteps" :name="currentSlotName"></slot>

			<!-- Default slot to provide content to dialog body
				if no step slot is provided.
				-->
			<slot v-else></slot>
		</div>

		<!-- Dialog Footer -->
		<template #footer>
			<div
				class="ext-growthExperiments-OnboardingDialog__footer"
			>
				<div
					class="ext-growthExperiments-OnboardingDialog__footer__actions"
				>
					<!-- Footer Actions Prev -->

					<!-- The first step of the dialog displays a checkbox
					if text content for the checkbox slot  is provided. -->
					<div
						v-if="currentStep === 1"
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
						v-if="currentStep === totalSteps"
						class="ext-growthExperiments-OnboardingDialog__footer__actions-next">
						<cdx-button
							weight="primary"
							action="progressive"
							@click="onLastStepBtnClick">
							<slot name="last-step-button-text">
								Close
							</slot>
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
		</template>
	</cdx-dialog>
</template>

<script>
import { ref, computed, toRef } from 'vue';
import { CdxDialog, CdxButton, CdxIcon, CdxCheckbox, useModelWrapper } from '@wikimedia/codex';
import { cdxIconNext, cdxIconPrevious } from '@wikimedia/codex-icons';
import OnboardingPaginator from './OnboardingPaginator.vue';

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
		 * The total number of steps the dialog will display
		 */
		totalSteps: {
			type: Number,
			default: 0

		},

		/**
		 * The number of the step to show when the dialog open
		 */
		initialStep: {
			type: Number,
			default: 1
		},

		/**
		 * Control whether to display or hide the paginator at the top of
		 * the dialog content when more than one step content is provided
		 */
		showPaginator: {
			type: Boolean,
			default: false
		},

		/**
		 * Whether the dialog is visible. Should be provided via a v-model:open
		 * binding in the parent scope.
		 */
		open: {
			type: Boolean,
			default: false
		}

	},
	emits: [ 'update:open', 'update:modelValue', 'update:currentStep' ],
	setup( props, { emit, slots, modelValue } ) {
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const currentStep = ref( props.initialStep );
		const currentSlotName = computed( () => `step${currentStep.value}` );
		const hasSteps = computed( () => !!slots.step1 );

		const onDialogOpenUpdate = ( newVal ) => {
			emit( 'update:open', newVal );
			currentStep.value = props.initialStep;

		};
		const onNextClick = () => {
			if ( currentStep.value + 1 <= props.totalSteps ) {
				currentStep.value++;
				emit( 'update:currentStep', currentStep.value );
			}
		};
		const onPrevClick = () => {
			if ( currentStep.value > 0 ) {
				currentStep.value--;
				emit( 'update:currentStep', currentStep.value );
			}
		};

		const onHeaderBtnClick = () => {
			emit( 'update:open', false );
			currentStep.value = props.initialStep;
		};

		const onLastStepBtnClick = () => {
			emit( 'update:open', false );
			currentStep.value = props.initialStep;

		};

		return {
			currentSlotName,
			currentStep,
			onNextClick,
			onPrevClick,
			cdxIconNext,
			cdxIconPrevious,
			onDialogOpenUpdate,
			hasSteps,
			onLastStepBtnClick,
			onHeaderBtnClick,
			wrappedOpen,
			modelValue
		};
	}
};
</script>

<style lang="less">
	@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
	@import './variables.less';

	.ext-growthExperiments-OnboardingDialog {
		position: relative;

		&__header {
			display: flex;
			justify-content: space-between;
			padding-left: @spacing-75;
			padding-top: @spacing-25;
			// This is the background color for the AddlinkDialog Images
			// and should be replaced as discussed on with a DS background color
			// See https://phabricator.wikimedia.org/T332567
			background-color: @onboardingBackgroundColor;

			&__title {
				font-size: @font-size-medium;
				line-height: 2.72em;
				font-weight: @font-weight-bold;
			}
		}

		&__content {
			&__paginator {
				// REVIEW Set top to 48px to adjust paginator position
				// to compensate the gutter between dialog's header and body.
				// This gutter is generated by Cdx-dialog gap value of 32px
				top: 48px;
				position: absolute;
			}
		}

		&__footer {
			border-top: @border-width-base @border-style-base @border-color-base;
			padding: @spacing-75;

			&__actions {
				display: flex;
				align-items: center;
				justify-content: space-between;
				font-size: @font-size-small;
			}
		}
	}

</style>
