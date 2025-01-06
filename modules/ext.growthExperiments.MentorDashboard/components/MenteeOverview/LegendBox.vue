<template>
	<div class="ext-growthExperiments-LegendBox">
		<h3>
			{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-headline' ) }}
		</h3>
		<div class="ext-growthExperiments-LegendBox__list">
			<div
				v-for="item in items"
				:key="item.label"
				class="ext-growthExperiments-LegendBox__list-item"
			>
				<cdx-icon
					class="ext-growthExperiments-LegendBox__list-item__legend-icon"
					:icon="icons[item.icon]"
					:icon-label="item.label"
				></cdx-icon>
				<span class="ext-growthExperiments-LegendBox__list-item__text">{{ item.label }}</span>
			</div>
		</div>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const icons = require( '../../../vue-components/icons.json' );

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxIcon
	},
	props: {
		items: { type: Array, required: true }
	},
	setup() {
		return {
			icons
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-LegendBox {
	&__list {
		display: grid;
		grid-template-columns: 1fr 1fr;
	}

	&__list-item {
		padding: 4px;

		&__legend-icon {
			opacity: @opacity-icon-subtle;
		}

		&__text {
			margin-left: 4px;
		}
	}
}
</style>
