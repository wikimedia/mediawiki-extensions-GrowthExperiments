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

			<div
				v-if="hasSteps"
				class="ext-growthExperiments-OnboardingDialog__content__transition"
				@touchstart="onTouchStart"
				@touchmove="onTouchMove">
				<transition :name="transitionName">
					<!-- Slot for the dialog steps.
						Content for each step can be provided by using the named slots:
						#step1, #step2, #step3, etc..
						-->
					<slot :name="currentSlotName"></slot>
				</transition>
			</div>

			<!-- Default slot to provide content to dialog body
				if no step slot is provided.
				-->
			<slot v-else></slot>
		</div>

		<!-- Dialog Footer -->
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
							<slot name="checkbox">
							</slot>
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
		OnboardingPaginator
	},
	props: {

		/**
		 * The total number of steps
		 */
		totalSteps: {
			type: Number,
			default: 0
		},

		/**
		 * The first step to show when the dialog is open
		 */
		initialStep: {
			type: Number,
			default: 1
		},

		isRtl: {
			type: Boolean,
			default: false
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
		},

		/**
		 * The initial value to use for the optional checkbox model. Should be
		 * provided via a v-model:is-checked binding in the parent scope.
		 */
		isChecked: {
			type: Boolean,
			default: false
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
		const transitionName = ref( props.isRtl ? 'left' : 'right' );
		const initialX = ref( null );
		const initialY = ref( null );

		watch( wrappedOpen, () => {
			if ( wrappedOpen.value === false ) {
				onClose();
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

		function navigateNext() {
			if ( currentStep.value < props.totalSteps ) {
				currentStep.value++;
				transitionName.value = props.isRtl ? 'left' : 'right';
				emit( 'update:currentStep', currentStep.value );
			}
			if ( greaterStepShown.value < props.totalSteps ) {
				greaterStepShown.value++;
			}
		}

		function navigatePrev() {
			if ( currentStep.value > 1 ) {
				currentStep.value--;
				transitionName.value = props.isRtl ? 'right' : 'left';
				emit( 'update:currentStep', currentStep.value );
			}
		}

		const onNextClick = () => navigateNext();
		const onPrevClick = () => navigatePrev();

		function onHeaderBtnClick() {
			closeSource.value = 'quiet';
			emit( 'update:open', false );
		}

		function onLastStepBtnClick() {
			closeSource.value = 'primary';
			emit( 'update:open', false );
		}

		function onTouchStart( e ) {
			const touchEvent = e.touches.item( 0 );
			initialX.value = touchEvent.clientX;
			initialY.value = touchEvent.clientY;
		}

		const isSwipeToLeft = ( touchEvent ) => {
			const newX = touchEvent.clientX;
			return initialX.value > newX;
		};
		const onSwipeToRight = () => {
			if ( props.isRtl ) {
				navigateNext();
			} else {
				navigatePrev();
			}
		};
		const onSwipeToLeft = () => {
			if ( props.isRtl ) {
				navigatePrev();
			} else {
				navigateNext();
			}
		};

		function onTouchMove( e ) {
			if ( !initialX.value || !initialY.value ) {
				return;
			}
			if ( isSwipeToLeft( e.touches.item( 0 ) ) ) {
				onSwipeToLeft();
			} else {
				onSwipeToRight();
			}
			initialX.value = null;
			initialY.value = null;
		}

		return {
			cdxIconNext,
			cdxIconPrevious,
			currentSlotName,
			currentStep,
			hasSteps,
			onDialogOpenUpdate,
			onHeaderBtnClick,
			onNextClick,
			onPrevClick,
			transitionName,
			onLastStepBtnClick,
			wrappedOpen,
			wrappedIsChecked,
			onTouchStart,
			onTouchMove
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

			&__transition {
				// REVIEW Position the transition steps relative to their wrapper element
				// to display the transition correctly
				position: relative;
				// stylelint-disable selector-class-pattern
				.right-enter-active,
				.right-leave-active,
				.left-enter-active,
				.left-leave-active {
					transition: all 500ms @animation-timing-function-base;
				}

				.right-enter-from {
					transform: translateX( @size-full );
				}

				.right-leave-to {
					transform: translateX( -@size-full );
				}

				.left-leave-to {
					transform: translateX( @size-full );
				}

				.left-enter-from {
					transform: translateX( -@size-full );
				}

				.right-leave-active,
				.left-leave-active {
					// REVIEW To correctly display the transition it is necesary
					// to position absolute each step relative to their wrapper element
					position: absolute;
				}
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
