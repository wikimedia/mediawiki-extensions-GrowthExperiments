<template>
	<div>
		<h3>
			{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-headline' ) }}
		</h3>
		<div class="legend-box__list">
			<div
				v-for="item in items"
				:key="item.label"
				class="legend-box__list-item"
			>
				<cdx-icon
					class="legend-icon"
					:icon="icons[item.icon]"
					:icon-label="item.label"
					@click="toggleStarred"
				></cdx-icon>
				<span class="legend-box__list-item__text">{{ item.label }}</span>
			</div>
		</div>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const icons = require( '../icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
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
.legend-box {
	&__list {
		display: grid;
		grid-template-columns: 1fr 1fr;
	}

	&__list-item {
		padding: 4px;

		.legend-icon {
			opacity: 0.66;
		}

		&__text {
			margin-left: 4px;
		}
	}
}
</style>
