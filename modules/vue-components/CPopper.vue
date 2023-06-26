<template>
	<div class="ext-growthExperiments-CPopper">
		<div
			ref="containerRef"
			class="ext-growthExperiments-CPopper__popover"
			tabindex="0"
			:style="popoverStyles"
		>
			<slot></slot>
		</div>
	</div>
</template>

<script>
const { onMounted, ref, computed } = require( 'vue' );
const { useComputedDirection } = require( '@wikimedia/codex' );
/*
 * GrowthExperiments common component
 *
 * Layout with absolute positioning to create overlays like
 * Dropdowns, Tooltips, Popovers.
 */
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	props: {
		/*
		 * The clipping point for the tooltip.
		 * One of null|'above'
		 */
		placement: {
			type: String,
			default: null
		},
		/*
		 * A ref of the popover trigger element.
		 * One of null|Proxy
		 */
		triggerRef: {
			type: Object,
			required: true
		}
	},
	setup( props ) {
		const containerRef = ref( null );
		const computedDir = useComputedDirection( containerRef );
		const containerBoundingRect = ref( null );
		const triggerBoundingRect = ref( props.triggerRef.getBoundingClientRect() );
		const maxWidth = computed( () => {
			if ( !containerBoundingRect.value ) {
				return;
			}
			const distanceToViewportEnd = computedDir.value === 'rtl' ?
				( window.innerWidth - triggerBoundingRect.value.right ) :
				triggerBoundingRect.value.left;

			return Math.min( distanceToViewportEnd, containerBoundingRect.value.width );
		} );
		const popoverStyles = computed( () => {
			const styles = {
				top: 0
			};
			if ( props.placement === 'above' ) {
				styles.top = 'unset';
				styles.bottom = `${props.triggerRef.clientHeight}px`;
			}
			if ( maxWidth.value ) {
				// Leave 16px of horizontal gutter between the content and
				// the end of the viewport
				styles[ 'max-width' ] = `${maxWidth.value + 16}px`;
			}
			return styles;
		} );
		onMounted( () => {
			containerRef.value.focus();
			containerBoundingRect.value = containerRef.value.getBoundingClientRect();
		} );
		return {
			containerRef,
			popoverStyles
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import '../utils/mixins.less';

.ext-growthExperiments-CPopper {
	position: relative;

	&__popover {
		position: absolute;
		// Avoid collision with the startediting dialog in the homepage
		// on a 2 column layout
		z-index: 2;
		right: 0;
		.popover-base();
		// Standard expects a title which has its own margin/padding
		padding-top: 0;
		width: max-content;

		&__close-button {
			.codex-icon-only-button( @color-subtle );
		}

		&__close-button-container {
			float: right;
		}
	}
}
</style>
