<template>
	<div class="ext-growthExperiments-NoResults">
		<cdx-icon
			class="ext-growthExperiments-NoResults__block-icon"
			:icon="icon"
			:icon-label="iconLabel"
		></cdx-icon>
		<div class="ext-growthExperiments-NoResults__info">
			<h3>{{ text }}</h3>
			<p v-if="description">
				{{ description }}
			</p>
		</div>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon
	},
	props: {
		description: { type: String, default: null },
		iconLabel: { type: String, required: true },
		text: { type: String, required: true },
		icon: { type: String, required: true }
	}
};
</script>

<style lang="less">
.ext-growthExperiments-NoResults {
	display: flex;
	flex-direction: column;
	align-items: center;

	&__block-icon {
		opacity: 0.2;

		> svg {
			width: 100px;
			height: 100px;
		}
	}

	&__info {
		text-align: center;
	}
}
</style>
