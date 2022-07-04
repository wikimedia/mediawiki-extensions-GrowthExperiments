<template>
	<div class="mentee-overview-info-box">
		<div v-click-outside="close">
			<cdx-button
				type="quiet"
				@click="togglePopover"
			>
				<cdx-icon
					class="info-icon"
					:icon="cdxIconInfo"
					:icon-label="$i18n( 'tbd-info' )"
				></cdx-icon>
			</cdx-button>
			<div class="mentee-overview-info-box__popover-container">
				<c-popper
					v-if="isOpen"
					class="mentee-overview-info-box__popover"
					@close="close"
				>
					<h3>
						{{
							$i18n(
								'growthexperiments-mentor-dashboard-mentee-overview-info-headline'
							)
						}}
					</h3>
					<p v-i18n-html="'growthexperiments-mentor-dashboard-mentee-overview-info-text'">
					</p>
					<legend-box v-if="legendItems.length" :items="legendItems"></legend-box>
				</c-popper>
			</div>
		</div>
	</div>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconInfo } = require( '../icons.json' );
const CPopper = require( '../CPopper/CPopper.vue' );
const LegendBox = require( './LegendBox.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon,
		CdxButton,
		CPopper,
		LegendBox
	},
	props: {
		legendItems: { type: Array, default: () => [] }
	},
	setup() {
		return {
			cdxIconInfo
		};
	},
	data() {
		return {
			isOpen: false
		};
	},
	methods: {
		close() {
			this.isOpen = false;
		},
		togglePopover() {
			this.isOpen = !this.isOpen;
		}
	}
};
</script>

<style lang="less">
@import '../variables.less';

.mentee-overview-info-box {
	display: flex;
	align-items: center;

	.info-icon {
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
