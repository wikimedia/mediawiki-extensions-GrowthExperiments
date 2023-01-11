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
		<div
			class="ext-growthExperiments-ScoreCard__label"
			:class="{
				'ext-growthExperiments-ScoreCard__label--with-info': hasInfoSlot
			}"
		>
			<c-text
				as="span"
				color="subtle"
				class="ext-growthExperiments-ScoreCard__label__text"
			>
				{{ label }}
			</c-text>
			<slot name="label-info"></slot>
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
			type: [ String, Object ],
			default: null
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
	setup( _props, { slots } ) {
		return {
			hasInfoSlot: Boolean( slots[ 'label-info' ] )
		};
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';
@topPadding: ( ( 24 / 14 ) * 1em );
@standardPadding: ( ( 16 / 14 ) * 1em );
@defaultLineHeight: ( ( 20 / 14 ) * 1em );

.ext-growthExperiments-ScoreCard {
	background-color: @background-color-progressive-subtle;
	padding: @topPadding @standardPadding @standardPadding @standardPadding;
	line-height: @defaultLineHeight;

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
		align-items: center;
		margin-top: ( ( 8 / 14 ) * 1em );
		margin-right: 0.5em;

		&--with-info {
			// Ensure vertical align of labels between cards with and without info button by
			// unsetting 32 min-height from CdxButton
			/* stylelint-disable-next-line selector-class-pattern */
			.cdx-button {
				min-height: unset;
			}

			.ext-growthExperiments-ScoreCard__label__text {
				vertical-align: middle;
			}
		}
	}
}
</style>
