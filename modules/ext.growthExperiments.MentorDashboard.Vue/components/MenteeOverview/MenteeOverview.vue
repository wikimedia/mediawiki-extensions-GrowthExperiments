<template>
	<section class="mentee-overview">
		<div class="mentee-overview__actions">
			<mentee-filters
				:data="filters"
				@update:filters="updateMenteeFilters"
			></mentee-filters>
			<div class="mentee-overview__actions__search">
				<mentee-search @update:selected="onMenteeSearchSelection"></mentee-search>
				<info-box :legend-items="legendItems"></info-box>
			</div>
		</div>
		<data-table
			v-if="hasData"
			class="mentee-overview__table"
			:caption-text="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-table-caption' )"
			:limit="limit"
			:columns="columns"
			:data="{ rows, totalPages, currentPage }"
			@toggle-starred="toggleStarred"
			@update:prev-page="navigateToPrevPage"
			@update:next-page="navigateToNextPage"
			@update:limit="updateLimit"
			@update:sorting="updateSorting"
		></data-table>
		<no-results
			v-else-if="menteesDataReady"
			class="mentee-overview__no-results"
			:text="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-headline' )"
			:icon-label="$i18n( 'tbd-no-results' )"
			:description="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-text' )"
		></no-results>
	</section>
</template>

<script>
const DataTable = require( '../DataTable/DataTable.vue' );
const MenteeSearch = require( './MenteeSearch.vue' );
const MenteeFilters = require( './MenteeFilters.vue' );
const NoResults = require( './NoResults.vue' );
const InfoBox = require( './InfoBox.vue' );
const MenteeOverviewApi = require( '../../../ext.growthExperiments.MentorDashboard/MenteeOverview/MenteeOverviewApi.js' );
const apiClient = new MenteeOverviewApi();

const MENTEES_TABLE_COLUMNS = [
	{
		label: 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-userinfo',
		icon: 'cdxIconUserActive',
		orderInLegend: 2,
		cellComponent: 'DataTableCellMentee',
		sortBy: 'last_active',
		data( mentee ) {
			return {
				userId: mentee.user_id,
				username: mentee.username,
				lastActive: mentee.last_active.human,
				isStarred: mentee.isStarred,
				userPageExists: mentee.userpage_exists
			};
		}
	},
	{
		label: 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-registration',
		icon: 'cdxIconClock',
		orderInLegend: 5,
		cellComponent: 'DataTableCellValue',
		cellProps: { align: 'center' },
		sortBy: 'registration',
		data( mentee ) {
			// TODO apply locale format?
			return mentee.registration.human;
		}
	},
	{
		label: 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-questions',
		icon: 'cdxIconHelp',
		orderInLegend: 1,
		cellComponent: 'DataTableCellLink',
		cellProps: { align: 'center' },
		sortBy: 'questions',
		data( mentee ) {
			return mentee.questions;
		}
	},
	{
		label: 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-editcount',
		icon: 'cdxIconEdit',
		orderInLegend: 3,
		cellComponent: 'DataTableCellLink',
		cellProps: { align: 'center' },
		sortBy: 'editcount',
		data( mentee ) {
			return mentee.editcount;
		}
	},
	{
		label: 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-reverts',
		icon: 'cdxIconEditUndo',
		orderInLegend: 4,
		cellComponent: 'DataTableCellLink',
		cellProps: { align: 'center' },
		sortBy: 'reverted',
		data( mentee ) {
			return mentee.reverted;
		}
	},
	{
		label: 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-blocks',
		icon: 'cdxIconBlock',
		orderInLegend: 6,
		cellComponent: 'DataTableCellLink',
		cellProps: { align: 'center' },
		sortBy: 'blocks',
		data( mentee ) {
			return mentee.blocks;
		}
	}
];

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		DataTable,
		MenteeFilters,
		MenteeSearch,
		NoResults,
		InfoBox
	},
	data() {
		return {
			currentPage: 1,
			columns: MENTEES_TABLE_COLUMNS.map( ( {
				cellComponent, cellProps, data, icon, key, label, sortBy, orderInLegend
			} ) => {
				const identity = ( x ) => x;
				return {
					orderInLegend,
					cellComponent,
					cellProps,
					key,
					icon,
					sortBy,
					label: this.$i18n( label ),
					data: data || identity
				};
			} )
		};
	},
	computed: {
		// TODO: use mapGetters ( with namespace ), cannot use spread operator?
		rows() {
			return this.$store.getters[ 'mentees/allMentees' ];
		},
		totalPages() {
			return this.$store.getters[ 'mentees/totalPages' ];
		},
		menteesDataReady() {
			return this.$store.getters[ 'mentees/isReady' ];
		},
		menteeFilters() {
			return this.$store.getters[ 'mentees/filters' ];
		},
		legendItems() {
			return this.columns.concat( {
				icon: 'cdxIconStar',
				orderInLegend: 0,
				label: this.$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-star' )
			} )
				.sort( ( a, b ) => a.orderInLegend - b.orderInLegend )
				.map( ( { icon, label } ) => ( {
					icon, label
				} ) );
		},
		limit() {
			return this.menteeFilters.limit;
		},
		filters() {
			const { editCountMin, editCountMax, activeDaysAgo, onlyStarred } = this.menteeFilters;
			return {
				editCountMin,
				editCountMax,
				activeDaysAgo,
				onlyStarred
			};
		},
		hasData() {
			return this.rows && this.rows.length > 0;
		}
	},
	methods: {
		// TODO move main logic to mentees store
		toggleStarred( eventData ) {
			const request = eventData.starred ?
				apiClient.unstarMentee.bind( apiClient, eventData.userId ) :
				apiClient.starMentee.bind( apiClient, eventData.userId );

			request()
				.then( apiClient.getStarredMentees.bind( apiClient ) )
				.then( ( starredMentees ) => {
					this.rows.forEach( function ( mentee ) {
						mentee.isStarred = starredMentees
							.indexOf( Number( mentee.user_id ) ) !== -1;
					} );
				}, ( err ) => {
					// TODO add UI error handling & logging
					// eslint-disable-next-line no-console
					console.error( 'failed', err );
				} );
		},
		onMenteeSearchSelection( value ) {
			this.$store.dispatch( 'mentees/getAllMentees', { prefix: value } );
		},
		navigateToPrevPage() {
			this.currentPage -= 1;
			this.$store.dispatch( 'mentees/getAllMentees', { page: this.currentPage } );
		},
		navigateToNextPage() {
			this.currentPage += 1;
			this.$store.dispatch( 'mentees/getAllMentees', { page: this.currentPage } );
		},
		updateLimit( value ) {
			this.$store.dispatch( 'mentees/getAllMentees', { limit: value } );
			this.$store.dispatch( 'mentees/savePresets' );
		},
		updateMenteeFilters( value ) {
			this.$store.dispatch( 'mentees/getAllMentees', Object.assign( {}, value, {
				page: 1
			} ) );
			this.$store.dispatch( 'mentees/savePresets' );
			this.currentPage = 1;
		},
		updateSorting( value ) {
			this.$store.dispatch( 'mentees/getAllMentees', value );
		}
	},
	created() {
		this.$store.dispatch( 'mentees/getAllMentees', this.menteeFilters );
	}
};
</script>

<style lang="less">
.mentee-overview {
	&__actions {
		display: flex;
		justify-content: space-between;
		padding: 8px 0;

		&__search {
			display: flex;
			justify-content: flex-end;
		}
	}

	&__no-results {
		background-color: #f8f9fa;
		border: 1px solid #c8ccd1;
		border-radius: 2px;
		text-align: center;
		padding: 93px 40px 151px;
	}
}
</style>
