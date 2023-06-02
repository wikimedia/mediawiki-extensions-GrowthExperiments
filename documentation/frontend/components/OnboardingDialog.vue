<template>
	<!-- eslint-disable vue/no-v-model-argument -->
	<cdx-dialog
		v-model:open="wrappedOpen"
		class="ext-growthExperiments-OnboardingDialog"
		title="Introduction"
		:hide-title="true"
		@update:open="( newVal ) => onDialogOpenUpdate( newVal )"
	>
		<!-- Dialog Header -->
		<template #header>
			<div
				class="ext-growthExperiments-OnboardingDialog__header"
			>
				<div class="ext-growthExperiments-OnboardingDialog__header__top">
					<h4 class="ext-growthExperiments-OnboardingDialog__header__top__title">
						<slot name="title"></slot>
					</h4>
					<cdx-button
						v-if="hasSteps"
						class="ext-growthExperiments-OnboardingDialog__header__top__button"
						weight="quiet"
						@click="onHeaderBtnClick"
					>
						<slot name="closeBtnText">
						</slot>
					</cdx-button>
					<cdx-button
						v-else
						weight="quiet"
						class="cdx-button--icon-only"
						aria-label="close"
						@click="onHeaderBtnClick">
						<cdx-icon :icon="cdxIconClose" icon-label="close"></cdx-icon>
					</cdx-button>
				</div>
				<!-- eslint-disable max-len -->
				<onboarding-stepper
					v-if="hasSteps"
					v-model:model-value="currentStep"
					class="ext-growthExperiments-OnboardingDialog__header__stepper"
					:total-steps="totalSteps"
					:label="stepperLabel"
				></onboarding-stepper>
				<!-- eslint-enable max-len -->
			</div>
		</template>
		<!-- Dialog Content -->
		<div>
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
		<!-- Dialog Footer -->
		<template #footer>
			<div class="ext-growthExperiments-OnboardingDialog__footer">
				<cdx-checkbox
					v-if="$slots.checkboxLabel"
					v-model="wrappedIsChecked"
					class="ext-growthExperiments-OnboardingDialog__footer__permanent-action"
					@update:model-value="newVal => wrappedIsChecked = newVal"
				>
					<slot name="checkboxLabel">
					</slot>
				</cdx-checkbox>

				<div
					class="ext-growthExperiments-OnboardingDialog__footer__navigation"
				>
					<div
						v-if="currentStep !== 1"
						class="ext-growthExperiments-OnboardingDialog__footer__navigation-prev"
					>
						<cdx-button
							aria-label="previous"
							@click="onPrevClick"
						>
							<cdx-icon :icon="cdxIconPrevious" icon-label="previous"></cdx-icon>
						</cdx-button>
					</div>
					<div
						v-if="currentStep === totalSteps"
						class="ext-growthExperiments-OnboardingDialog__footer__navigation--next"
					>
						<cdx-button
							weight="primary"
							action="progressive"
							@click="onStartBtnClick"
						>
							<!-- eslint-disable max-len -->
							<slot name="startBtnText">
							</slot>
							<!-- eslint-enable max-len -->
						</cdx-button>
					</div>
					<div
						v-else
						class="ext-growthExperiments-OnboardingDialog__footer__navigation--next"
					>
						<cdx-button
							weight="primary"
							action="progressive"
							class="cdx-button--icon-only"
							aria-label="next"
							@click="onNextClick"
						>
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
import { cdxIconNext, cdxIconPrevious, cdxIconClose } from '@wikimedia/codex-icons';
import OnboardingStepper from './OnboardingStepper.vue';
import MultiPane from './MultiPane.vue';

/**
 * @name OnboardingDialog
 *
 * @description A multi-step dialog that gives the user detailed information
 * to complete a task. Different steps are are navigable with arrow buttons back and forth.
 *
 */

export default {
	name: 'OnboardingDialog',

	components: {
		CdxDialog,
		CdxButton,
		CdxIcon,
		CdxCheckbox,
		MultiPane,
		OnboardingStepper
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
		 * Text label for the stepper component
		 */
		stepperLabel: {
			type: String,
			default: ''
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
			emit( 'update:currentStep', currentStep.value );
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
			if ( slots.checkboxLabel ) {
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

		function onStartBtnClick() {
			closeSource.value = 'primary';
			emit( 'update:open', false );
		}

		return {
			cdxIconClose,
			cdxIconNext,
			cdxIconPrevious,
			currentStep,
			currentSlotName,
			hasSteps,
			multiPaneRef,
			onDialogOpenUpdate,
			onHeaderBtnClick,
			onNextClick,
			onPrevClick,
			onStartBtnClick,
			wrappedOpen,
			wrappedIsChecked
		};
	}
};
</script>

<style lang="less">
@import '../node_modules/@wikimedia/codex-design-tokens/dist/theme-wikimedia-ui.less';
@import './variables.less';
@import './mixins.less';

.ext-growthExperiments-OnboardingDialog {
	.ext-growthExperiments-onboarding-dialog-color();
	.ext-growthExperiments-onboarding-dialog-size();
	// stylelint-disable-next-line selector-class-pattern
	&.cdx-dialog {
		gap: 0;
	}
	// stylelint-disable-next-line selector-class-pattern
	.cdx-dialog__body {
		padding: 0;
	}

	&__header {
		padding-inline-start: @spacing-150;
		padding-inline-end: @spacing-75;
		padding-top: @spacing-75;
		padding-bottom: @spacing-100;

		&__top {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;

			&__title {
				padding-top: @spacing-50;
				font-size: @font-size-medium;
				line-height: @line-height-xx-small;
				font-weight: @font-weight-bold;
				padding-bottom: @spacing-75;
			}

			&__button {
				min-width: @onboardingCloseButtonMinWidht;
			}
		}
	}

	&__footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		flex-wrap: wrap;
		padding: @spacing-100 @spacing-150 @spacing-35 @spacing-150;

		&__navigation {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			font-size: @font-size-small;
			flex-grow: 1;
			margin-bottom: @spacing-75;

			&--prev,
			&--next {
				padding-inline-start: @spacing-50;
			}
		}
	}
}
</style>
