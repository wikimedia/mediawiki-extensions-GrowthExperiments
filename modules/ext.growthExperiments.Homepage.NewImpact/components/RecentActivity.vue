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
					{{ $i18n( 'growthexperiments-homepage-impact-recent-activity-contribs-count-text', contribs.count ) }}
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
const moment = require( 'moment' );
const CText = require( '../../vue-components/CText.vue' );
const StreakGraph = require( './StreakGraph.vue' );

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
		},
		dateFormat: {
			type: String,
			default: 'MMM D'
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
			return moment().format( this.dateFormat );
		},
		startLabel() {
			return moment()
				.subtract( this.timeFrame - 1, 'days' )
				.format( this.dateFormat );
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
			const date = moment( this.contribs.keys[ index ] ).format( this.dateFormat );
			if ( this.contribs.entries[ index ] > 0 ) {
				return this.$i18n(
					'growthexperiments-homepage-impact-recent-activity-streak-data-text',
					mw.language.convertNumber( this.contribs.entries[ index ] ),
					date
				);
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
