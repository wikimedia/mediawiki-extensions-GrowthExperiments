<template>
	<div class="ext-growthExperiments-CInfoBox">
		<div v-click-outside="close">
			<cdx-button
				type="quiet"
				:aria-label="iconLabel"
				@click="togglePopover"
			>
				<cdx-icon
					class="ext-growthExperiments-CInfoBox__info-icon"
					:icon="icon"
				></cdx-icon>
			</cdx-button>
			<div class="ext-growthExperiments-CInfoBox__popover-container">
				<c-popper
					v-if="isOpen"
					class="ext-growthExperiments-CInfoBox__popover"
					:icon="closeIcon"
					:icon-label="closeIconLabel"
					@close="close"
				>
					<slot></slot>
				</c-popper>
			</div>
		</div>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const CPopper = require( './CPopper.vue' );
const clickOutside = require( './directives/click-outside.directive.js' );
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
		CdxIcon,
		CdxButton,
		CPopper
	},
	directives: {
		clickOutside
	},
	props: {
		icon: {
			type: Object,
			required: true
		},
		iconLabel: {
			type: String,
			required: true
		},
		closeIcon: {
			type: Object,
			default: null
		},
		closeIconLabel: {
			type: String,
			default: null
		}
	},
	setup() {
		const isOpen = ref( false );
		const close = () => {
			isOpen.value = false;
		};
		const togglePopover = () => {
			isOpen.value = !isOpen.value;
		};
		return {
			isOpen,
			close,
			togglePopover
		};
	}
};
</script>

<style lang="less">
@import './variables.less';

.ext-growthExperiments-CInfoBox {
	display: flex;
	align-items: center;

	&__info-icon {
		// REVIEW how to affect stoke-width
		opacity: 0.66;

		> svg {
			cursor: pointer;
			width: 24px;
			height: 24px;
		}
	}

	&__popover {
		// REVIEW conflicts with .growthexperiments-mentor-dashboard-container
		// .growthexperiments-mentor-dashboard-module h3 rules
		&& h3 {
			color: @colorBase10;
		}
	}
}
</style>
