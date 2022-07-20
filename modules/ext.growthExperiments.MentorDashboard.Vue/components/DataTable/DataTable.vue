<template>
	<div class="data-table">
		<table class="data-table__table">
			<caption v-if="captionText">
				{{ captionText }}
			</caption>
			<thead>
				<tr>
					<th
						v-for="( field, i ) in fields"
						:key="i"
						@click="onSortColumnClick( field )"
					>
						<div class="data-table__table__header">
							<cdx-icon
								v-if="field.icon"
								class="data-table__table__icon"
								:icon="icons[ field.icon ]"
								:icon-label="field.label"
							></cdx-icon>
							<span
								class="sort-icon"
								:class="{
									[order]: sortBy === field.sortBy
								}"
							></span>
						</div>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="( row, j ) in limitedRows" :key="j">
					<td
						v-for="( field, k ) in fields"
						:key="k"
					>
						<component
							:is="field.cellComponent"
							v-bind="field.cellProps( row )"
							@toggle-starred="$emit( 'toggle-starred', $event )"
						></component>
					</td>
				</tr>
			</tbody>
		</table>
		<div class="data-table__footer-actions">
			<data-table-limit
				:limit-options="LIMIT_OPTIONS"
				:limit="limit"
				@update="onLimitChange"
			></data-table-limit>
			<data-table-pagination
				:current-page="data.currentPage"
				:total-pages="data.totalPages"
				@next="$emit( 'update:next-page' )"
				@prev="$emit( 'update:prev-page' )"
			></data-table-pagination>
		</div>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const icons = require( '../icons.json' );
const DataTableCellValue = require( './DataTableCellValue.vue' );
const DataTableCellLink = require( './DataTableCellLink.vue' );
const DataTableCellMentee = require( '../MenteeOverview/DataTableCellMentee.vue' );
const DataTablePagination = require( './DataTablePagination.vue' );
const DataTableLimit = require( './DataTableLimit.vue' );
const LIMIT_OPTIONS = [ 5, 10, 15, 20, 25 ];

// TODO: possibly create one or more Vuex stores for the table fields/rows/filters
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon,
		DataTableLimit,
		DataTablePagination,
		DataTableCellLink,
		DataTableCellValue,
		DataTableCellMentee
	},
	props: {
		captionText: { type: String, default: undefined },
		columns: { type: Array, required: true },
		data: { type: Object, default: () => ( { rows: [], totalPages: 0 } ) },
		limit: { type: Number, default: LIMIT_OPTIONS[ 1 ] }
	},
	emits: [
		'toggle-starred',
		'update:sorting',
		'update:limit',
		'update:prev-page',
		'update:next-page'
	],
	data() {
		return {
			icons: icons,
			LIMIT_OPTIONS: LIMIT_OPTIONS,
			sortBy: null,
			order: null,
			fields: this.columns.map( ( column ) => {
				return Object.assign( {}, column, {
					cellProps( mentee ) {
						return Object.assign( {}, column.cellProps, {
							value: column.data( mentee )
						} );
					}
				} );
			} )
		};
	},
	computed: {
		limitedRows() {
			return this.data.rows.slice( 0, this.limit );
		}
	},
	methods: {
		onSortColumnClick( field ) {
			// unset order when changing the column to filter by
			// to start from neutral state
			if ( this.sortBy !== null && this.sortBy !== field.sortBy ) {
				this.order = null;
			}
			if ( this.order === null ) {
				this.order = 'ascending';
			} else if ( this.order === 'ascending' ) {
				this.order = 'descending';
			} else if ( this.order === 'descending' ) {
				this.order = null;
			}
			this.sortBy = field.sortBy;
			this.$emit( 'update:sorting', {
				order: this.order === 'ascending' ? 'asc' : 'desc',
				sortBy: this.sortBy
			} );
		},
		onLimitChange( value ) {
			this.$emit( 'update:limit', value );
		}
	}
};
</script>

<style lang="less">
@import '../variables.less';

.data-table {
	&__footer-actions {
		width: 100%;
		display: flex;
		padding: 8px 0;
		justify-content: flex-end;
	}

	&__table {
		border: 1px solid #c8ccd1;
		border-radius: 2px;
		overflow: hidden;
		border-spacing: 0;
		width: 100%;
		table-layout: fixed;

		&__icon {
			// FIXME the output color should be achieved by
			// modifying the icon color not the opacity;
			opacity: 0.66;
		}

		&__header {
			display: grid;
			grid-template-columns: 7fr 1fr;
		}

		caption {
			text-align: left;
			color: @color-accessory;
			padding: @spacing-small 0;
		}

		thead {
			th {
				border-bottom: 1px solid #c8ccd1;
				cursor: pointer;
			}

			.sort-icon {
				background-repeat: no-repeat;
				background-position: center right;
				padding-right: 21px;
				background-image: url( ../../../../../../resources/src/jquery.tablesorter.styles/images/sort_both.svg );

				&.ascending {
					background-image: url( ../../../../../../resources/src/jquery.tablesorter.styles/images/sort_up.svg );
				}

				&.descending {
					background-image: url( ../../../../../../resources/src/jquery.tablesorter.styles/images/sort_down.svg );
				}
			}

			th:first-child {
				width: 30%;
			}

			tr {
				background-color: #f8f9fa;
				height: 47px;
			}
		}

		tbody {
			td {
				border-bottom: 1px solid #c8ccd1;
			}

			tr:last-child td {
				// without this, border from the whole table and for the row would be combined
				border-bottom: unset;
			}
		}
	}
}
</style>
