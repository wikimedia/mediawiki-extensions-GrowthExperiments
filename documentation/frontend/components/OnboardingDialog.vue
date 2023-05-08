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
			<div class="ext-growthExperiments-OnboardingDialog__header">
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
				v-if="hasSteps"
				v-model:current-step="currentStep"
				class="ext-growthExperiments-OnboardingDialog__content__paginator"
				:total-steps="totalSteps"
			></onboarding-paginator>
			<multi-pane
				v-if="hasSteps"
				ref="multiPaneRef"
				v-model:current-step="currentStep"
				:total-steps="totalSteps"
				:is-rtl="isRtl"
				@update:current-step="( newVal ) => currentStep = newVal"
			>
				<slot :name="currentSlotName"></slot>
			</multi-pane>
			<!-- Default slot to provide content to dialog body
				if no step slot is provided.
				-->
			<slot v-else></slot>
		</div>

		<template #footer>
			<div class="ext-growthExperiments-OnboardingDialog__footer">
				<div class="ext-growthExperiments-OnboardingDialog__footer__actions">
					<!-- Footer Actions Prev -->

					<!-- The first step of the dialog displays a checkbox
					if text content for the checkbox slot  is provided. -->
					<div
						v-if="currentStep === 1"
						class="ext-growthExperiments-OnboardingDialog__footer__actions-prev"
					>
						<!-- eslint-disable-next-line max-len -->
						<cdx-checkbox
							v-if="$slots.checkbox"
							v-model="wrappedIsChecked"
							@update:model-value="newVal => wrappedIsChecked = newVal"
						>
							<slot name="checkbox"></slot>
						</cdx-checkbox>
					</div>
					<!-- All the following steps display an Icon only button to navigate
					to the previous step -->
					<div
						v-else
						class="ext-growthExperiments-OnboardingDialog__footer__actions-prev"
					>
						<cdx-button
							aria-label="previous"
							@click="onPrevClick"
						>
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
							@click="onLastStepBtnClick"
						>
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
import { ref, computed, toRef, watch } from 'vue';
import { CdxDialog, CdxButton, CdxIcon, CdxCheckbox, useModelWrapper } from '@wikimedia/codex';
import { cdxIconNext, cdxIconPrevious } from '@wikimedia/codex-icons';
import OnboardingPaginator from './OnboardingPaginator.vue';
import MultiPane from './MultiPane.vue';

/**
 * @name OnboardingDialog
 *
 *
 * @description This is a multi-step dialog that gives the user detailed information
 * to complete an structured task.
 *
 * Different steps are are navigable with arrow buttons back and forth.
 * Displays a paginator that shows to the user the current progress in the dialog.
 *
 * A main title can be provided via #title slot and the text in the top button
 * can be customized via #headerbtntext slot.
 * Step content is provided via #step1, #step2, #step3, etc...
 * Footer actions can be customized via #checkbox and #last-step-button-text slots respectively,
 * to display a checkbox in the first step and a button with custom text in the last one.
 */

export default {
	name: 'OnboardingDialog',

	components: {
		CdxDialog,
		CdxButton,
		CdxIcon,
		CdxCheckbox,
		MultiPane,
		OnboardingPaginator
	},
	props: {
		/**
		 * The first step to show when the dialog is open
		 */
		initialStep: {
			type: Number,
			default: 1
		},
		/**
		 * The initial value to use for the optional checkbox model. Should be
		 * provided via a v-model:is-checked binding in the parent scope.
		 */
		isChecked: {
			type: Boolean,
			default: false
		},
		isRtl: {
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
		},
		/**
		 * The total number of steps
		 */
		totalSteps: {
			type: Number,
			default: 0
		}
	},
	emits: [ 'update:open', 'update:is-checked', 'update:currentStep', 'close' ],
	setup( props, { emit, slots } ) {
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const wrappedIsChecked = useModelWrapper( toRef( props, 'isChecked' ), emit, 'update:is-checked' );
		const currentStep = ref( props.initialStep );
		const greaterStepShown = ref( props.initialStep );
		const closeSource = ref( undefined );
		const currentSlotName = computed( () => `step${currentStep.value}` );
		const hasSteps = computed( () => !!slots.step1 );
		const multiPaneRef = ref( null );

		watch( wrappedOpen, () => {
			if ( wrappedOpen.value === false ) {
				onClose();
			}
		} );

		watch( currentStep, () => {
			if ( greaterStepShown.value < props.totalSteps ) {
				greaterStepShown.value++;
			}
		} );

		function onDialogOpenUpdate( newVal ) {
			emit( 'update:open', newVal );
		}

		function onClose() {
			const closeResultObj = { closeSource: closeSource.value || 'unkown' };
			if ( slots.step1 ) {
				closeResultObj.currentStep = currentStep.value;
				closeResultObj.greaterStep = greaterStepShown.value;
			}
			if ( slots.checkbox ) {
				closeResultObj.isChecked = wrappedIsChecked.value;
			}
			emit( 'close', closeResultObj );
			currentStep.value = props.initialStep;
			greaterStepShown.value = props.initialStep;
			closeSource.value = undefined;
			wrappedIsChecked.value = false;
		}

		function onPrevClick() {
			multiPaneRef.value.navigatePrev();
		}
		function onNextClick() {
			multiPaneRef.value.navigateNext();
		}

		function onHeaderBtnClick() {
			closeSource.value = 'quiet';
			emit( 'update:open', false );
		}

		function onLastStepBtnClick() {
			closeSource.value = 'primary';
			emit( 'update:open', false );
		}

		return {
			cdxIconNext,
			cdxIconPrevious,
			currentSlotName,
			currentStep,
			hasSteps,
			multiPaneRef,
			onDialogOpenUpdate,
			onHeaderBtnClick,
			onNextClick,
			onPrevClick,
			onLastStepBtnClick,
			wrappedOpen,
			wrappedIsChecked
		};
	}
};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
@import './variables.less';

.ext-growthExperiments-OnboardingDialog {
	position: relative;
	// stylelint-disable-next-line selector-class-pattern
	.cdx-dialog__body {
		// REVIEW Overwrite CdxDialog overflow-x to avoid
		// showing the horizontal scroll bar during transitions
		overflow-x: hidden;
	}

	&__header {
		display: flex;
		justify-content: space-between;
		padding-top: @spacing-25;
		padding-inline-start: @spacing-100;
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
			z-index: @z-index-top;
		}
	}

	&__footer {
		border-top: @border-width-base @border-style-base @border-color-base;
		padding: @spacing-100;

		&__actions {
			display: flex;
			align-items: center;
			justify-content: space-between;
			font-size: @font-size-small;
		}
	}
}
</style>
