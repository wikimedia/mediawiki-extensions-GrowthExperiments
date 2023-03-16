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
				'ext-growthExperiments-ScoreCard__label--with-info': hasInfoContent
			}"
		>
			<c-text
				as="span"
				color="subtle"
				class="ext-growthExperiments-ScoreCard__label__text"
			>
				{{ label }}
			</c-text>
			<span
				v-if="hasInfoContent"
			>
				<c-popover
					:close-icon="cdxIconClose"
					:close-icon-label="$i18n( 'growthexperiments-info-tooltip-close-label' ).text()"
					@open="$emit( 'open' )"
					@close="$emit( 'close' );"
				>
					<template #trigger="{ onClick }">
						<cdx-button
							weight="quiet"
							class="ext-growthExperiments-ScoreCards__info-button"
							:aria-label="infoIconLabel"
							@click="onClick"
						>
							<cdx-icon
								:icon="cdxIconInfo"
							></cdx-icon>
						</cdx-button>
					</template>
					<template #content>
						<slot name="info-content"></slot>
					</template>
				</c-popover>
			</span>
		</div>
	</div>
</template>

<script>
const { useSlots } = require( 'vue' );
const {
	cdxIconClose,
	cdxIconInfo
} = require( './icons.json' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const CText = require( './CText.vue' );
const CPopover = require( './CPopover.vue' );

// Uses the following message keys:
// growthexperiments-info-tooltip-close-label
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon,
		CPopover,
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
		},
		infoIconLabel: {
			type: String,
			default: ''
		}
	},
	emits: [
		'close',
		'open'
	],
	setup() {
		const hasInfoContent = Boolean( useSlots()[ 'info-content' ] );
		return {
			hasInfoContent,
			cdxIconClose,
			cdxIconInfo
		};
	}
};
</script>
