<template>
	<div class="ext-growthExperiments-Popover">
		<div v-click-outside="close">
			<div ref="triggerRef">
				<slot
					name="trigger"
					@click="togglePopover"
				></slot>
			</div>
			<div class="ext-growthExperiments-Popover__popover-container">
				<c-popper
					v-if="isOpen"
					class="ext-growthExperiments-Popover__popover"
					:placement="placement"
					:trigger-ref="triggerRef"
					:icon="closeIcon"
					:icon-label="closeIconLabel"
					@close="close"
				>
					<slot name="content"></slot>
				</c-popper>
			</div>
		</div>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
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
		CPopper
	},
	directives: {
		clickOutside
	},
	props: {
		closeIcon: {
			type: [ String, Object ],
			default: null
		},
		closeIconLabel: {
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
		}
	},
	emits: [
		'toggle'
	],
	setup( _props, { emit } ) {
		const triggerRef = ref( false );
		const isOpen = ref( false );
		const close = () => {
			isOpen.value = false;
		};
		const togglePopover = () => {
			isOpen.value = !isOpen.value;
			emit( 'toggle', isOpen.value );

		};
		return {
			isOpen,
			close,
			togglePopover,
			triggerRef
		};
	}
};
</script>

<style lang="less">
@import './variables.less';

.ext-growthExperiments-Popover {
	display: flex;
	align-items: center;

	&__popover {
		// REVIEW conflicts with .growthexperiments-mentor-dashboard-container
		// .growthexperiments-mentor-dashboard-module h3 rules
		&& h3 {
			color: @colorBase10;
		}
	}
}
</style>