<template>
	<section class="ext-growthExperiments-MenteeOverview">
		<cdx-toggle-button
			ref="infoToggleButton"
			v-model="showPopover"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-icon-label' ).text()"
			:quiet="true"
			class="ext-growthExperiments-MenteeOverview__info-button"
		>
			<cdx-icon
				:icon="cdxIconInfo"
			></cdx-icon>
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
			:anchor="windowName !== 'MenteeOverviewJestTests' ? infoToggleButton : null"
			placement="bottom-start"
			:render-in-place="true"
			:title="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-info-headline' ).text()"
			:use-close-button="true"
			:icon="cdxIconInfo"
		>
			<div class="ext-growthExperiments-MenteeOverview__info-content">
				<p v-i18n-html="'growthexperiments-mentor-dashboard-mentee-overview-info-text'">
				</p>
				<legend-box v-if="legendItems.length" :items="legendItems"></legend-box>
			</div>
		</cdx-popover>
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
			v-if="hasData && !menteesDataHasError"
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
			v-else-if="menteesDataReady && !menteesDataHasError && doesFilterOutMentees"
			class="ext-growthExperiments-MenteeOverview__no-results"
			:icon="cdxIconError"
			:text="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-headline' ).text()"
			:icon-label="$i18n( 'tbd-no-results' ).text()"
			:description="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-text' ).text()"
		></no-results>
		<no-results
			v-else-if="menteesDataReady && !menteesDataHasError && !doesFilterOutMentees"
			class="ext-growthExperiments-MenteeOverview__no-results"
			:icon="cdxIconClock"
			:text="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-headline' ).text()"
			:icon-label="$i18n( 'tbd-no-results' ).text()"
			:description="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-text' ).text()"
		></no-results>
		<no-results
			v-else-if="menteesDataReady && menteesDataHasError"
			class="ext-growthExperiments-MenteeOverview__no-results"
			:icon="cdxIconError"
			:text="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-error-title' ).text()"
			:icon-label="$i18n( 'tbd-no-results' ).text()"
			:description="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-error-description' ).text()"
		></no-results>
	</section>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxIcon, CdxToggleButton, CdxPopover } = require( '@wikimedia/codex' );
const DataTable = require( '../DataTable/DataTable.vue' );
const MenteeSearch = require( './MenteeSearch.vue' );
const MenteeFilters = require( './MenteeFilters.vue' );
const NoResults = require( './NoResults.vue' );
const LegendBox = require( './LegendBox.vue' );
const { cdxIconError, cdxIconClock, cdxIconInfo } = require( '../../../vue-components/icons.json' );
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
				userTalkExists: mentee.usertalk_exists,
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
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxIcon,
		DataTable,
		MenteeFilters,
		MenteeSearch,
		NoResults,
		LegendBox,
		CdxToggleButton,
		CdxPopover
	},
	setup() {
		const showPopover = ref( false );
		const infoToggleButton = ref( null );
		return {
			showPopover,
			infoToggleButton,
			cdxIconError,
			cdxIconClock,
			cdxIconInfo
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
					// eslint-disable-next-line mediawiki/msg-doc
					label: this.$i18n( label ).text(),
					data: data || identity
				};
			} )
		};
	},
	computed: {
		windowName() {
			return window.name;
		},
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
		menteesDataHasError() {
			return this.$store.getters[ 'mentees/hasError' ];
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
					this.rows.forEach( ( mentee ) => {
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
@import 'mediawiki.skin.variables.less';
@import '../../../utils/mixins.less';

.ext-growthExperiments-MenteeOverview {
	container-type: inline-size;

	&__info-button {
		.codex-icon-only-button(@color-subtle, 24px);
		margin-top: @spacing-50;
		margin-right: @spacing-50;
		position: absolute;
		top: 0;
		right: 0;
	}

	&__info-content {
		max-width: 410px;
	}

	&__actions {
		display: flex;
		justify-content: space-between;
		align-items: center;
		align-self: stretch;
		gap: @spacing-75;
		padding: @spacing-50 0 @spacing-75 0;

		&__search {
			display: flex;
			justify-content: flex-end;
			max-width: @size-1600;
		}
	}

	&__no-results {
		background-color: @background-color-neutral-subtle;
		border: @border-subtle;
		border-radius: @border-radius-base;
		text-align: center;
		padding: 93px 40px 151px;
	}
}

/* stylelint-disable-next-line selector-class-pattern */
.skin-minerva {
	.ext-growthExperiments-MenteeOverview {
		// Override Minerva's default top margin for `.content table` elements
		table {
			margin-top: 0;
		}

		&__actions {
			.ext-growthExperiments-MenteeFilters {
				display: flex;
				flex-direction: column;
				align-items: flex-start;
			}

			&__search {
				align-items: center;
				flex: 1 0 0;
			}
		}
	}
}

</style>
