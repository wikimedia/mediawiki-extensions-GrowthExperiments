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
