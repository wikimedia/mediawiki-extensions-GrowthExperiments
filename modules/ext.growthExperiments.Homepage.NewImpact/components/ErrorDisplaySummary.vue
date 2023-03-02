<template>
	<div class="ext-growthExperiments-ErrorDisplaySummary">
		<div class="ext-growthExperiments-ErrorDisplaySummary__content">
			<cdx-icon
				class="ext-growthExperiments-ErrorDisplaySummary__icon"
				:icon="cdxIconAlert"
			></cdx-icon>
			<c-text
				class="ext-growthExperiments-ErrorDisplaySummary__title"
				as="h3"
				weight="bold"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-error-data-not-found-text', userName ).text() }}
			</c-text>
		</div>
		<info-boxes class="ext-growthExperiments-ErrorDisplaySummary__boxes"></info-boxes>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const { CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconAlert } = require( '../../vue-components/icons.json' );
const CText = require( '../../vue-components/CText.vue' );
const InfoBoxes = require( './InfoBoxes.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		CdxIcon,
		InfoBoxes
	},
	setup() {
		const userName = inject( 'RELEVANT_USER_USERNAME' );
		return {
			userName,
			cdxIconAlert
		};
	}
};
</script>

<style lang="less">
@import ( reference ) '../../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';

.ext-growthExperiments-ErrorDisplaySummary {
	margin-bottom: 1em;

	&__content {
		display: flex;
		align-items: baseline;
	}

	&__boxes {
		margin-top: 1em;
	}

	&__icon {
		color: @color-warning;
		margin-right: 0.6em;
	}
}
</style>
