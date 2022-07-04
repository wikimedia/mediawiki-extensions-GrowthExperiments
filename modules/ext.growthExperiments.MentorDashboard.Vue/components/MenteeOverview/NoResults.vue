<template>
	<div class="mentee-overview-no-results">
		<cdx-icon
			class="block-icon"
			:icon="cdxIconError"
			:icon-label="iconLabel"
		></cdx-icon>
		<div class="mentee-overview-no-results-info">
			<h3>{{ text }}</h3>
			<p v-if="description">
				{{ description }}
			</p>
		</div>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconError } = require( '../icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon
	},
	props: {
		description: { type: String, default: null },
		iconLabel: { type: String, required: true },
		text: { type: String, required: true }
	},
	setup() {
		return {
			cdxIconError
		};
	}
};
</script>

<style lang="less">
.mentee-overview-no-results {
	display: flex;
	flex-direction: column;
	align-items: center;

	.block-icon {
		opacity: 0.2;

		> svg {
			width: 100px;
			height: 100px;
		}
	}

	&-info {
		text-align: center;
	}
}
</style>
