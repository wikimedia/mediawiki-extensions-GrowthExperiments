<template>
	<div
		ref="rootElementRef"
		class="ext-growthExperiments-MultiPane"
		@touchstart="onTouchStart"
		@touchmove="onTouchMove">
		<transition :name="computedTransitionName">
			<slot v-if="$slots.step1" :name="currentSlotName"></slot>
			<slot v-else></slot>
		</transition>
	</div>
</template>

<script>
const { computed, ref, toRef, watch, defineComponent } = require( 'vue' );
const { useModelWrapper, useComputedDirection } = require( '@wikimedia/codex' );
const TRANSITION_NAMES = {
	LEFT: 'left',
	RIGHT: 'right',
};

// @vue/component
module.exports = exports = defineComponent( {
	name: 'MultiPane',
	props: {
		// eslint-disable-next-line vue/no-unused-properties
		currentStep: {
			type: Number,
			default: 0,
		},
		totalSteps: {
			type: Number,
			default: 1,
		},
	},
	emits: [ 'update:currentStep' ],
	setup( props, { emit, expose } ) {
		const rootElementRef = ref( null );
		const computedDir = useComputedDirection( rootElementRef );
		const wrappedCurrentStep = useModelWrapper( toRef( props, 'currentStep' ), emit, 'update:currentStep' );
		const prevStep = ref( 1 );
		const initialX = ref( null );
		const initialY = ref( null );
		const currentNavigation = ref( null );
		const currentSlotName = computed( () => `step${ wrappedCurrentStep.value }` );
		const isRtl = computed( () => computedDir.value === 'rtl' );
		const computedTransitionSet = computed( () => isRtl.value ?
			{ next: TRANSITION_NAMES.LEFT, prev: TRANSITION_NAMES.RIGHT } :
			{ next: TRANSITION_NAMES.RIGHT, prev: TRANSITION_NAMES.LEFT } );
		const computedTransitionName = computed(
			() => computedTransitionSet.value[ currentNavigation.value ],
		);

		function navigate( actionName ) {
			currentNavigation.value = actionName;
			prevStep.value = actionName === 'next' ? prevStep.value + 1 : prevStep.value - 1;
		}

		/**
		 * Convenience method to navigate forward from another component.
		 *
		 * @public
		 */
		function navigateNext() {
			if ( wrappedCurrentStep.value < props.totalSteps ) {
				navigate( 'next' );
				wrappedCurrentStep.value++;
			}
		}

		/**
		 * Convenience method to navigate backwards from another component.
		 *
		 * @public
		 */
		function navigatePrev() {
			if ( wrappedCurrentStep.value > 1 ) {
				navigate( 'prev' );
				wrappedCurrentStep.value--;
			}
		}

		// React to changes on the currentStep model, needed if the
		// parent does not use the convenience methods navigatePrev, navigateNext
		// but modifies the currentStep model directly
		watch( wrappedCurrentStep, () => {
			if ( prevStep.value < wrappedCurrentStep.value ) {
				navigate( 'next' );
			} else if ( prevStep.value > wrappedCurrentStep.value ) {
				navigate( 'prev' );
			}
		} );

		function onTouchStart( e ) {
			const touchEvent = e.touches[ 0 ];
			initialX.value = touchEvent.clientX;
			initialY.value = touchEvent.clientY;
		}

		const isSwipeToLeft = ( touchEvent ) => {
			const newX = touchEvent.clientX;
			return initialX.value > newX;
		};

		const onSwipeToRight = () => {
			if ( isRtl.value === true ) {
				navigateNext();
			} else {
				navigatePrev();
			}
		};
		const onSwipeToLeft = () => {
			if ( isRtl.value === true ) {
				navigatePrev();
			} else {
				navigateNext();
			}
		};

		function onTouchMove( e ) {
			if ( !initialX.value || !initialY.value ) {
				return;
			}
			if ( isSwipeToLeft( e.touches[ 0 ] ) ) {
				onSwipeToLeft();
			} else {
				onSwipeToRight();
			}
			initialX.value = null;
			initialY.value = null;
		}

		// Make navigate methods available on parent component through a ref
		expose( { navigatePrev, navigateNext } );
		return {
			computedTransitionName,
			currentSlotName,
			onTouchStart,
			onTouchMove,
			rootElementRef,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import './variables.less';

.ext-growthExperiments-MultiPane {
	position: relative;
	overflow: hidden hidden;
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
		// Use `calc()` for negative calculation to not rely on Less, but standard CSS.
		transform: translateX( calc( -1 * @size-full ) );
	}

	.left-leave-to {
		transform: translateX( @size-full );
	}

	.left-enter-from {
		transform: translateX( calc( -1 * @size-full ) );
	}

	.right-leave-active,
	.left-leave-active {
		position: absolute;
	}
}
</style>
