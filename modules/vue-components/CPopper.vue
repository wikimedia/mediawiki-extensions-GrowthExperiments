<template>
	<div class="ext-growthExperiments-CPopper">
		<div
			ref="containerRef"
			class="ext-growthExperiments-CPopper__popover"
			tabindex="0"
			@keyup.esc="$emit( 'close' )"
		>
			<div class="ext-growthExperiments-CPopper__popover__close-button-container">
				<cdx-button
					type="quiet"
					class="ext-growthExperiments-CPopper__popover__close-button"
					@click="$emit( 'close', $event )"
				>
					<cdx-icon
						:icon="icon"
						:icon-label="iconLabel"
						class="ext-growthExperiments-CPopper__popover__close-icon"
					></cdx-icon>
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
			required: true
		},
		iconLabel: {
			type: String,
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
	}
};
</script>

<style lang="less">
@import './variables.less';

.ext-growthExperiments-CPopper {
	position: relative;

	&__popover {
		position: absolute;
		z-index: 1;
		top: 0;
		right: 0;
		.popover-base();
		// Standard expects a title which has its own margin/padding
		padding-top: 0;

		&__close-icon {
			opacity: 0.66;
		}

		&__close-button-container {
			float: right;
		}
	}
}
</style>
