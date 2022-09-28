<template>
	<div class="ext-growthExperiments-ScoreCard">
		<div class="ext-growthExperiments-ScoreCard__data-display">
			<cdx-icon
				class="ext-growthExperiments-ScoreCard__data-display__icon"
				:icon="icon"
				:icon-label="$i18n( 'growthexperiments-tbd' )"
			></cdx-icon>
			<slot></slot>
		</div>
		<c-text
			size="medium"
			color="subtle"
			class="ext-growthExperiments-ScoreCard__label"
		>
			{{ label }}
		</c-text>
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
		color: @color-subtle;
		margin-right: 0.5em;
	}
}
</style>
