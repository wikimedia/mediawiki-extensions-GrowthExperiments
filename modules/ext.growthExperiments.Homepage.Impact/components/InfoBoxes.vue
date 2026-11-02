<template>
	<div class="ext-growthExperiments-InfoBoxes">
		<div class="ext-growthExperiments-InfoBoxes__box">
			<c-text
				size="medium"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-last-edit-text' ).text() }}
			</c-text>
			<c-text
				size="medium"
				weight="bold"
			>
				{{ lastEditFormattedTimeAgo }}
			</c-text>
		</div>
		<div class="ext-growthExperiments-InfoBoxes__box">
			<c-text
				size="medium"
			>
				{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-best-streak-text' ).text() }}
			</c-text>
			<c-text
				size="medium"
				weight="bold"
			>
				{{ longestEditingStreakCountText }}
			</c-text>
		</div>
	</div>
</template>

<script>
const CText = require( '../../vue-components/CText.vue' );
const { NO_DATA_CHARACTER } = require( '../constants.js' );
const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CText,
	},
	props: {
		data: {
			type: Object,
			default: null,
		},
	},
	computed: {
		lastEditFormattedTimeAgo() {
			return this.data ?
				formatRelativeTimeOrDate( new Date( this.data.lastEditTimestamp * 1000 ) ) :
				NO_DATA_CHARACTER;
		},
		longestEditingStreakCountText() {
			if ( !this.data ) {
				return NO_DATA_CHARACTER;
			}
			const bestStreakDaysLocalisedCount = this.$filters.convertNumber(
				this.data.longestEditingStreak.datePeriod.days,
			);
			return this.$i18n(
				'growthexperiments-homepage-impact-recent-activity-streak-count-text',
				bestStreakDaysLocalisedCount,
			).text();
		},
	},
};
</script>

<style lang="less">
.ext-growthExperiments-InfoBoxes {
	display: flex;

	&__box {
		flex: 1;
	}
}
</style>
