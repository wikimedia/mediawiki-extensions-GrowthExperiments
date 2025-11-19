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
				<div class="ext-growthExperiments-OnboardingDialog__header__top_row">
					<onboarding-stepper
						v-if="hasSteps"
						v-model:model-value="currentStep"
						class="ext-growthExperiments-OnboardingDialog__header__stepper"
						:total-steps="totalSteps"
						:label="stepperLabel"
					></onboarding-stepper>
					<span class="close-all-button">
						<cdx-button
							v-if="hasSteps"
							class="ext-growthExperiments-OnboardingDialog__header__top__button"
							weight="quiet"
							@click="onHeaderBtnClick"
						>
							{{ closeButtonText }}
						</cdx-button>
						<cdx-button
							v-else
							weight="quiet"
							class="cdx-button--icon-only"
							aria-label="close"
							@click="onHeaderBtnClick">
							<cdx-icon :icon="cdxIconClose" icon-label="close"></cdx-icon>
						</cdx-button>
					</span>
				</div>
				<div class="ext-growthExperiments-OnboardingDialog__header__title">
					<slot name="title"></slot>
				</div>
			</div>
		</template>
		<!-- Dialog Content -->
		<div>
			<multi-pane
				v-if="hasSteps"
				ref="multiPaneRef"
				v-model:current-step="currentStep"
				:total-steps="totalSteps"
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
					v-if="checkboxLabel !== null"
					v-model="wrappedIsChecked"
					class="ext-growthExperiments-OnboardingDialog__footer__permanent-action"
					@update:model-value="newVal => wrappedIsChecked = newVal"
				>
					{{ checkboxLabel }}
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
							{{ startButtonText }}
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
const { ref, computed, toRef, watch, defineComponent } = require( 'vue' );
const { CdxDialog, CdxButton, CdxIcon, CdxCheckbox, useModelWrapper } = require( '@wikimedia/codex' );
const { cdxIconNext, cdxIconPrevious, cdxIconClose } = require( './codex-icons.json' );
const OnboardingStepper = require( './OnboardingStepper.vue' );
const MultiPane = require( './MultiPane.vue' );

/**
 * @name OnboardingDialog
 *
 * @description A multi-step dialog that gives the user detailed information
 * to complete a task. Different steps are are navigable with arrow buttons back and forth.
 */

// @vue/component
module.exports = exports = defineComponent( {
	name: 'OnboardingDialog',

	components: {
		CdxDialog,
		CdxButton,
		CdxIcon,
		CdxCheckbox,
		MultiPane,
		OnboardingStepper,
	},
	props: {
		/**
		 * The first step to show when the dialog is open
		 */
		initialStep: {
			type: Number,
			default: 1,
		},
		/**
		 * The initial value to use for the optional checkbox model. Should be
		 * provided via a v-model:is-checked binding in the parent scope.
		 */
		// eslint-disable-next-line vue/no-unused-properties
		isChecked: {
			type: Boolean,
			default: false,
		},
		/**
		 * Whether the dialog is visible. Should be provided via a v-model:open
		 * binding in the parent scope.
		 */
		// eslint-disable-next-line vue/no-unused-properties
		open: {
			type: Boolean,
			default: false,
		},
		/**
		 * Text label for the stepper component
		 */
		stepperLabel: {
			type: String,
			default: '',
		},
		/**
		 * The total number of steps
		 */
		totalSteps: {
			type: Number,
			default: 0,
		},
		closeButtonText: {
			type: String,
			required: true,
		},
		startButtonText: {
			type: String,
			required: true,
		},
		checkboxLabel: {
			type: String,
			default: null,
		},
	},
	emits: [ 'update:open', 'update:is-checked', 'update:currentStep', 'close' ],
	setup( props, { emit, slots } ) {
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const wrappedIsChecked = useModelWrapper( toRef( props, 'isChecked' ), emit, 'update:is-checked' );
		const currentStep = ref( props.initialStep );
		const greaterStepShown = ref( props.initialStep );
		const closeSource = ref( undefined );
		const currentSlotName = computed( () => `step${ currentStep.value }` );
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
			const closeResultObj = { closeSource: closeSource.value || 'unknown' };
			if ( slots.step1 ) {
				closeResultObj.currentStep = currentStep.value;
				closeResultObj.greaterStep = greaterStepShown.value;
			}
			if ( props.checkboxLabel ) {
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
			wrappedIsChecked,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import './variables.less';
@import './mixins.less';

.ext-growthExperiments-OnboardingDialog {
	.ext-growthExperiments-onboarding-dialog-color();
	.ext-growthExperiments-onboarding-dialog-size();

	/**
	* FIXME
	* Overwrites of Cdx-dialog classes to avoid padding in the dialog body
	* and to keep the same padding value when the dialog body content is and is not scrollable
	* https://phabricator.wikimedia.org/T336265
	*/

	// stylelint-disable selector-class-pattern
	&.cdx-dialog {
		.cdx-dialog__body,
		.cdx-dialog__header {
			padding: 0;
		}

		.cdx-dialog__footer {
			padding-top: 0;
		}
	}
	// stylelint-enable selector-class-pattern

	&__header {
		&__top_row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: @size-100 @size-75 @size-0 @size-150;
			background: @background-color-neutral;
			height: 48px;
		}

		&__stepper {
			margin: 0;
		}

		&__title {
			background: @background-color-neutral;
			padding: @size-100 @size-150 @size-75;
			color: @color-base;
			font-family: 'Inter', sans-serif;
			font-style: normal;
			font-weight: normal;
			font-size: 20px;
			line-height: 30px;
			display: flex;
			align-items: center;
		}
	}

	&__footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		flex-wrap: wrap;

		&__navigation {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			font-size: @font-size-small;
			flex-grow: 1;

			&--prev,
			&--next {
				padding-inline-start: @spacing-50;
			}
		}
	}
}
</style>
