<template>
	<div
		class="ext-growthExperiments-RecentActivity"
		:class="{
			'ext-growthExperiments-RecentActivity--mobile': isMobile
		}"
	>
		<div class="ext-growthExperiments-RecentActivity__streak">
			<div class="ext-growthExperiments-RecentActivity__streak__highlight">
				<c-text
					as="span"
					weight="bold"
					size="xxl"
					class="ext-growthExperiments-RecentActivity__streak__highlight__number"
				>
					{{ $filters.convertNumber( contribs.count ) }}
				</c-text>
				<c-text
					as="span"
					class="ext-growthExperiments-RecentActivity__streak__highlight__text"
				>
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-contribs-count-text', contribs.count ).text() }}
				</c-text>
			</div>
			<div class="ext-growthExperiments-RecentActivity__streak__graphic">
				<streak-graph
					v-if="contribs"
					:columns="timeFrame"
					:get-column-value-fn="getStreakColumnValue"
					:get-column-title-fn="getStreakColumnTitle"
					:start-label="startLabel"
					:end-label="endLabel"
				></streak-graph>
			</div>
		</div>
	</div>
</template>

<script>
const { getIntlLocale } = require( '../../utils/Utils.js' );
const CText = require( '../../vue-components/CText.vue' );
const StreakGraph = require( './StreakGraph.vue' );
const DATE_FORMAT = { month: 'short', day: 'numeric' };

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CText,
		StreakGraph
	},
	props: {
		isMobile: {
			type: Boolean,
			default: false
		},
		contribs: {
			type: Object,
			default: null
		},
		timeFrame: {
			type: Number,
			required: true
		}
	},
	computed: {
		minContribs() {
			return Math.min( ...this.contribs.entries );
		},
		maxContribs() {
			return Math.max( ...this.contribs.entries );
		},
		endLabel() {
			const intlLocale = getIntlLocale();
			return new Intl.DateTimeFormat( intlLocale, DATE_FORMAT ).format( new Date() );
		},
		startLabel() {
			const date = new Date();
			date.setDate( date.getDate() - ( this.timeFrame - 1 ) );
			const intlLocale = getIntlLocale();
			return new Intl.DateTimeFormat( intlLocale, DATE_FORMAT ).format( date );
		}
	},
	methods: {
		getStreakColumnValue( index ) {
			const value = this.contribs.entries[ index ];
			const range = Math.max( this.maxContribs - this.minContribs, 1 );
			const scale = 100 / range;
			return value * scale;
		},
		getStreakColumnTitle( index ) {
			const intlLocale = getIntlLocale();
			const date = new Intl.DateTimeFormat( intlLocale, DATE_FORMAT )
				.format( new Date( this.contribs.keys[ index ] ) );
			if ( this.contribs.entries[ index ] > 0 ) {
				return this.$i18n(
					'growthexperiments-homepage-impact-recent-activity-streak-data-text',
					mw.language.convertNumber( this.contribs.entries[ index ] ),
					date
				).text();
			}

			return date;
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-RecentActivity {
	display: flex;
	flex-direction: column;

	&__streak {
		display: flex;
		align-items: center;
		flex-wrap: wrap;

		&__highlight {
			display: flex;
			flex-direction: column;
			margin-right: 0.8em;

			&__number {
				margin-right: 0.2em;
			}
		}

		&__graphic {
			padding-top: 0.5em;
			flex: 5;
		}
	}

	&--mobile {
		.ext-growthExperiments-RecentActivity__streak {
			flex-direction: column;
			align-items: stretch;

			&__highlight {
				flex-direction: row;
				align-items: baseline;
			}
		}
	}
}
</style>
