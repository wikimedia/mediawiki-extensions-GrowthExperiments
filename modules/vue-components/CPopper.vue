<template>
	<div class="ext-growthExperiments-CPopper">
		<div
			ref="containerRef"
			class="ext-growthExperiments-CPopper__popover"
			tabindex="0"
			:style="popoverStyles"
			@keyup.esc="$emit( 'close' )"
		>
			<div
				v-if="icon"
				class="ext-growthExperiments-CPopper__popover__close-button-container">
				<cdx-button
					type="quiet"
					:aria-label="iconLabel"
					class="ext-growthExperiments-CPopper__popover__close-button"
					@click="$emit( 'close', $event )"
				>
					<cdx-icon :icon="icon"></cdx-icon>
				</cdx-button>
			</div>
			<slot></slot>
		</div>
	</div>
</template>

<script>
const { CdxButton, CdxIcon } = require( '@wikimedia/codex' );
const { onMounted, ref } = require( 'vue' );
/*
 * GrowthExperiments common component
 *
 * Layout with absolute positioning to create overlays like
 * Dropdowns, Tooltips, Popovers.
 */
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon
	},
	props: {
		icon: {
			type: Object,
			default: null
		},
		iconLabel: {
			type: String,
			default: null
		},
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
	emits: [ 'close' ],
	setup() {
		const containerRef = ref( null );
		onMounted( () => {
			containerRef.value.focus();
		} );
		return {
			containerRef
		};
	},
	computed: {
		popoverStyles() {
			if ( this.placement === 'above' ) {
				return {
					top: 'unset',
					bottom: `${this.triggerRef.clientHeight}px`
				};
			}
			return {
				top: 0
			};
		}
	}
};
</script>

<style lang="less">
@import './variables.less';

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

		&__close-button {
			.codex-icon-only-button( @color-subtle );
		}

		&__close-button-container {
			float: right;
		}
	}
}
</style>
