<template>
	<div class="c-popper">
		<div
			ref="containerRef"
			class="c-popper__popover"
			tabindex="0"
			@keyup.esc="$emit( 'close' )"
		>
			<div class="close-button-container">
				<cdx-button
					type="quiet"
					class="close-button"
					@click="$emit( 'close', $event )"
				>
					<cdx-icon
						:icon="cdxIconClose"
						icon-label="close"
						class="close-icon"
					></cdx-icon>
				</cdx-button>
			</div>
			<slot></slot>
		</div>
	</div>
</template>

<script>
// Consider wrapping library  https://popper.js.org/ in this
// component to facilitate the calculus of viewport boundaries
// and offsets for all components using absolute positioning,
// ie: Dropdowns, Tooltips, Popovers
const { CdxButton, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconClose } = require( '../icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon
	},
	emits: [ 'close' ],
	setup() {
		return {
			cdxIconClose
		};
	},
	mounted() {
		this.$refs.containerRef.focus();
	}
};
</script>

<style lang="less">
@import '../variables.less';

.c-popper {
	position: relative;

	&__popover {
		width: 430px;
		position: absolute;
		z-index: 1;
		top: 0;
		right: 0;
		.popover-base();
		// Standard expects a title which has its own margin/padding
		padding-top: 0;

		.close-icon {
			opacity: 0.66;
		}

		.close-button-container {
			float: right;
		}
	}
}
</style>
