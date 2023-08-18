<template>
	<div class="ext-growthExperiments-Popover">
		<div v-click-outside="closeIfOpen">
			<div ref="triggerRef">
				<slot
					name="trigger"
					@click="togglePopover"
				></slot>
			</div>
			<div
				class="ext-growthExperiments-Popover__surface-container"
				@keyup.esc="close"
			>
				<c-popper
					v-if="isOpen"
					:placement="placement"
					:trigger-ref="triggerRef"
					:icon="closeIcon"
					:icon-label="closeIconLabel"
				>
					<div
						v-if="title"
						class="ext-growthExperiments-Popover__surface"
					>
						<div class="ext-growthExperiments-Popover__surface__header">
							<div class="ext-growthExperiments-Popover__surface__header__title">
								<cdx-icon
									v-if="headerIcon"
									class="ext-growthExperiments-Popover__surface__header__icon"
									:icon="headerIcon"
									:icon-label="headerIconLabel"
								></cdx-icon>
								<span :class="titleClass">{{ title }}</span>
							</div>
							<cdx-button
								weight="quiet"
								:aria-label="closeIconLabel"
								class="ext-growthExperiments-CPopper__popover__close-button"
								@click="close"
								@keyup.esc="close"
							>
								<cdx-icon :icon="closeIcon"></cdx-icon>
							</cdx-button>
						</div>
						<div class="ext-growthExperiments-Popover__surface__content">
							<slot name="content"></slot>
						</div>
					</div>
					<slot v-else name="content"></slot>
				</c-popper>
			</div>
		</div>
	</div>
</template>

<script>
const { ref, computed } = require( 'vue' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const CPopper = require( './CPopper.vue' );
const clickOutside = require( './directives/click-outside.directive.js' );
/*
 * GrowthExperiments common component
 *
 * Popover like interface which displays content in a floating
 * surface when clicking on an element. It provides a
 * "trigger" slot meant to place the element that will cause
 * the floating surface to show when clicked. It also provides
 * a "content" slot to place arbitrary content to display inside
 * the surface.
 */
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxButton,
		CdxIcon,
		CPopper
	},
	directives: {
		clickOutside
	},
	props: {
		/*
		 * The icon to use in the close popover button
		 */
		closeIcon: {
			type: [ String, Object ],
			default: null
		},
		/*
		 * The label for the close icon
		 */
		closeIconLabel: {
			type: String,
			default: null
		},
		/*
		 * The icon to place as an inline prefix of the title
		 */
		headerIcon: {
			type: [ String, Object ],
			default: null
		},
		/*
		 * The label for the inline prefix icon
		 */
		headerIconLabel: {
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
		 * The tooltip title
		 */
		title: {
			type: String,
			default: null
		},
		/*
		 * The CSS class name to apply to the title element
		 */
		titleClass: {
			type: String,
			default: null
		}
	},
	emits: [
		'close',
		'open',
		'toggle'
	],
	setup( _props, { emit, expose } ) {
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

		expose( { close, togglePopover } );
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

	&__surface {
		// REVIEW conflicts with .growthexperiments-mentor-dashboard-container
		// .growthexperiments-mentor-dashboard-module h3 rules
		&& h3 {
			color: @color-base;
		}

		&__header {
			min-height: @spacing-200;
			display: flex;
			justify-content: space-between;
			// Vertical alignment with the close button
			align-items: center;
			padding-top: @spacing-25;
			padding-left: @spacing-75;
			padding-right: @spacing-75;

			&__icon {
				margin-right: 1em;
			}

			&__title {
				display: flex;
				// Vertical alignment with the header icon
				align-items: center;
			}
		}

		&__content {
			// Should match header padding
			padding-left: @spacing-75;
			padding-right: @spacing-75;
			padding-bottom: @spacing-25;
		}
	}
}
</style>
