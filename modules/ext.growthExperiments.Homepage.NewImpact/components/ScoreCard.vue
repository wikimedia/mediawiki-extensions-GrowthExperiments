<template>
	<div class="ext-growthExperiments-ScoreCard">
		<div class="ext-growthExperiments-ScoreCard__data-display">
			<cdx-icon
				class="ext-growthExperiments-ScoreCard__data-display__icon"
				:icon="icon"
				:icon-label="iconLabel"
			></cdx-icon>
			<slot></slot>
		</div>
		<div class="ext-growthExperiments-ScoreCard__label">
			<c-text
				as="span"
				color="subtle"
			>
				{{ label }}
			</c-text>
			<span class="ext-growthExperiments-ScoreCard__label__info-icon">
				<slot name="label-info"></slot>
			</span>
		</div>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const CText = require( '../../vue-components/CText.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon,
		CText
	},
	props: {
		icon: {
			type: [ Function, Object ],
			default: () => ( {} )
		},
		label: {
			type: String,
			required: true
		},
		iconLabel: {
			type: String,
			required: true
		}
	},
	setup() {
		return {

		};
	},
	computed: {
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';

.ext-growthExperiments-ScoreCard {
	background-color: @background-color-progressive-subtle;
	padding: 1em;

	&__data-display {
		display: flex;
		align-items: center;

		&__icon {
			color: @color-subtle;
			margin-right: 0.5em;
		}
	}

	&__label {
		display: inline-flex;
		width: 100%;
		justify-content: space-between;
		align-items: baseline;
		margin-right: 0.5em;
		margin-top: 0.5em;

		&__info-icon {
			// Subtract 10px to the vertical positioning of the label icon to align labels
			// in scorecards with and without icon
			margin-top: -10px; // quiet button height 32px - label line height 22.4px ~= 10px
		}
	}
}
</style>
