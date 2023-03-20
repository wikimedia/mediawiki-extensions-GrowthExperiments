<template>
	<div class="ext-growthExperiments-Popover">
		<div v-click-outside="closeIfOpen">
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
const { ref, computed } = require( 'vue' );
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
		'close',
		'open',
		'toggle'
	],
	setup( _props, { emit } ) {
		const triggerRef = ref( false );
		const isOpen = ref( false );
		const close = () => {
			isOpen.value = false;
			emit( 'toggle', false );
			emit( 'close' );
		};
		const togglePopover = () => {
			isOpen.value = !isOpen.value;
			emit( 'toggle', isOpen.value );
			if ( isOpen.value ) {
				emit( 'open' );
			} else {
				emit( 'close' );
			}
		};
		const closeIfOpen = computed( () => isOpen.value ? close : () => {} );
		return {
			isOpen,
			close,
			closeIfOpen,
			togglePopover,
			triggerRef
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-Popover {
	display: flex;
	align-items: center;

	&__popover {
		// REVIEW conflicts with .growthexperiments-mentor-dashboard-container
		// .growthexperiments-mentor-dashboard-module h3 rules
		&& h3 {
			color: @color-base;
		}
	}
}
</style>
