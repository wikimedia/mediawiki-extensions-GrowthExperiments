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
				<cdx-toggle-button
					ref="infoToggleButton"
					v-model="showPopover"
					:aria-label="infoIconLabel"
					:quiet="true"
					class="ext-growthExperiments-ScoreCards__info-button"
				>
					<cdx-icon :icon="cdxIconInfo"></cdx-icon>
				</cdx-toggle-button>
				<!--
					CdxPopover uses the floating-ui library in a way that causes infinite recursion when
					mounted in JSDOM. Shallow rendering the component in turn fails if an anchor reference
					is provided, because vue-test-utils is unable to stringify the HTML element held within
					the ref. Work around the situation by using shallow rendering in tests and use a well-known
					window name to avoid passing the anchor in this case.
				-->
				<cdx-popover
					v-model:open="showPopover"
					:anchor="windowName !== 'CScoreCardJestTests' ? infoToggleButton : null"
					placement="bottom-start"
					:render-in-place="true"
					:title="iconLabel"
					:use-close-button="true"
					:icon="infoHeaderIcon || cdxIconInfo"
					@update:open=" ( val ) => $emit( val ? 'open' : 'close' )"
				>
					<slot name="info-content"></slot>
				</cdx-popover>
			</span>
		</div>
	</div>
</template>

<script>
const { computed, ref, useSlots } = require( 'vue' );
const { CdxIcon, CdxToggleButton, CdxPopover } = require( '@wikimedia/codex' );
const { cdxIconInfo } = require( './icons.json' );
const CText = require( './CText.vue' );

// Uses the following message keys:
// growthexperiments-info-tooltip-close-label
// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxIcon,
		CdxToggleButton,
		CdxPopover,
		CText
	},
	props: {
		/*
		 * The main scorecard icon
		 */
		icon: {
			type: [ String, Object ],
			default: null
		},
		/*
		 * The label for the main scorecard icon
		 */
		iconLabel: {
			type: String,
			required: true
		},
		/*
		 * The icon placed as inline prefix of the information
		 * popover title. Will use the same label as defined in infoIconLabel.
		 */
		infoHeaderIcon: {
			type: [ String, Object ],
			default: null
		},
		/*
		 * The label for the information icon
		 */
		infoIconLabel: {
			type: String,
			default: ''
		},
		/*
		 * The label displayed below the main scorecard icon
		 */
		label: {
			type: String,
			required: true
		}
	},
	emits: [
		'close',
		'open'
	],
	setup() {
		const hasInfoContent = Boolean( useSlots()[ 'info-content' ] );
		const infoToggleButton = ref( null );
		const showPopover = ref( false );
		const windowName = computed( () => window.name );
		return {
			hasInfoContent,
			infoToggleButton,
			cdxIconInfo,
			showPopover,
			windowName
		};
	}
};
</script>
