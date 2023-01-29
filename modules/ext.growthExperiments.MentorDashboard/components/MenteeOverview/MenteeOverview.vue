<template>
	<section class="ext-growthExperiments-MenteeOverview">
		<div class="ext-growthExperiments-MenteeOverview__info-box-wrapper">
			<c-popover
				class="ext-growthExperiments-MenteeOverview__info-box"
				:icon="cdxIconInfo"
				:icon-label="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-icon-label' ).text()"
				:close-icon="cdxIconClose"
			>
				<template #trigger="{ onClick }">
					<cdx-button
						type="quiet"
						class="ext-growthExperiments-MenteeOverview__info-button"
						:aria-label="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-icon-label' ).text()"
						@click="onClick"
					>
						<cdx-icon :icon="cdxIconInfo"></cdx-icon>
					</cdx-button>
				</template>
				<template #content>
					<h3>
						{{
							$i18n(
								'growthexperiments-mentor-dashboard-mentee-overview-info-headline'
							)
						}}
					</h3>
					<p v-i18n-html="'growthexperiments-mentor-dashboard-mentee-overview-info-text'">
					</p>
					<legend-box v-if="legendItems.length" :items="legendItems"></legend-box>
				</template>
			</c-popover>
		</div>
		<div class="ext-growthExperiments-MenteeOverview__actions">
			<mentee-filters
				:data="filters"
				@update:filters="updateMenteeFilters"
			></mentee-filters>
			<div class="ext-growthExperiments-MenteeOverview__actions__search">
				<mentee-search @update:selected="onMenteeSearchSelection"></mentee-search>
			</div>
		</div>
		<data-table
			v-if="hasData"
			class="ext-growthExperiments-MenteeOverview__table"
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
			v-else-if="menteesDataReady && doesFilterOutMentees"
			class="ext-growthExperiments-MenteeOverview__no-results"
			:icon="cdxIconError"
			:text="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-headline' ).text()"
			:icon-label="$i18n( 'tbd-no-results' ).text()"
			:description="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-text' ).text()"
		></no-results>
		<no-results
			v-else-if="menteesDataReady && !doesFilterOutMentees"
			class="ext-growthExperiments-MenteeOverview__no-results"
			:icon="cdxIconClock"
			:text="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-headline' ).text()"
			:icon-label="$i18n( 'tbd-no-results' ).text()"
			:description="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-text' ).text()"
		></no-results>
	</section>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const DataTable = require( '../DataTable/DataTable.vue' );
const MenteeSearch = require( './MenteeSearch.vue' );
const MenteeFilters = require( './MenteeFilters.vue' );
const NoResults = require( './NoResults.vue' );
const CPopover = require( '../../../vue-components/CPopover.vue' );
const LegendBox = require( './LegendBox.vue' );
const { cdxIconError, cdxIconClock, cdxIconInfo, cdxIconClose } = require( '../icons.json' );
const apiClient = require( '../../store/MenteeOverviewApi.js' );

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
				userPageExists: mentee.userpage_exists,
				userIsHidden: mentee.user_is_hidden
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
			if ( !mentee.registration ) {
				// NOTE: mentee.registration can be null for users who registered pre-2005 (T314807)
				return mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-registered-unknown' );
			}
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
		CdxIcon,
		CdxButton,
		DataTable,
		MenteeFilters,
		MenteeSearch,
		NoResults,
		CPopover,
		LegendBox
	},
	setup() {
		return {
			cdxIconError,
			cdxIconClock,
			cdxIconInfo,
			cdxIconClose
		};
	},
	data() {
		return {
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
					label: this.$i18n( label ).text(),
					data: data || identity
				};
			} )
		};
	},
	computed: {
		// TODO: use mapGetters ( with namespace ), cannot use spread operator?
		currentPage() {
			return this.$store.getters[ 'mentees/currentPage' ];
		},
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
				label: this.$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-star' ).text()
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
		},
		doesFilterOutMentees() {
			return this.$store.getters[ 'mentees/doesFilterOutMentees' ];
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
			this.$store.dispatch( 'mentees/getAllMentees', { page: this.currentPage - 1 } );
		},
		navigateToNextPage() {
			this.$store.dispatch( 'mentees/getAllMentees', { page: this.currentPage + 1 } );
		},
		updateLimit( value ) {
			const currentOffset = ( this.currentPage - 1 ) * this.limit;
			const newPage = Math.floor( currentOffset / value ) + 1;
			this.$store.dispatch( 'mentees/getAllMentees', { limit: value, page: newPage } );
			this.$store.dispatch( 'mentees/savePresets' );
		},
		updateMenteeFilters( value ) {
			// eslint-disable-next-line compat/compat
			this.$store.dispatch( 'mentees/getAllMentees', Object.assign( {}, value, {
				page: 1
			} ) );
			this.$store.dispatch( 'mentees/savePresets' );
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
@import ( reference ) '../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';
@import '../../../utils/mixins.less';

.ext-growthExperiments-MenteeOverview {
	&__info-box {
		position: absolute;
		top: -64px;
		right: -16px;

		/* stylelint-disable-next-line selector-class-pattern */
		.skin-minerva & {
			top: -114px;
		}

		&-wrapper {
			position: relative;
		}
	}

	&__info-button {
		.codex-icon-only-button( @color-subtle, 24px);
	}

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
