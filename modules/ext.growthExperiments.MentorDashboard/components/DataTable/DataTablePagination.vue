<template>
	<div class="ext-growthExperiments-DataTablePagination">
		<span>
			{{ paginationText }}
		</span>
		<cdx-button
			weight="quiet"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-page-prev-icon-label' ).text()"
			:disabled="currentPage === 1"
			@click="$emit( 'prev', $event )"
		>
			<cdx-icon
				class="ext-growthExperiments-DataTablePagination__arrow-icon"
				:icon="cdxIconPrevious"
			></cdx-icon>
		</cdx-button>
		<cdx-button
			weight="quiet"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-page-next-icon-label' ).text()"
			:disabled="currentPage === totalPages"
			@click="$emit( 'next', $event )"
		>
			<cdx-icon
				class="ext-growthExperiments-DataTablePagination__arrow-icon"
				:icon="cdxIconNext"
			></cdx-icon>
		</cdx-button>
	</div>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconNext, cdxIconPrevious } = require( '../../../vue-components/icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon,
		CdxButton
	},
	props: {
		currentPage: { type: Number, default: 1 },
		totalPages: { type: Number, required: true }
	},
	emits: [ 'prev', 'next' ],
	setup() {
		return {
			cdxIconNext,
			cdxIconPrevious
		};
	},
	computed: {
		paginationText() {
			return this.$i18n(
				'growthexperiments-mentor-dashboard-mentee-overview-page-counter',
				this.$filters.convertNumber( this.currentPage ),
				this.$filters.convertNumber( this.totalPages )
			);
		}
	}
};
</script>

<style lang="less">
@import ( reference ) '../../../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';

.ext-growthExperiments-DataTablePagination {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: @spacing-25 @spacing-75;

	&__arrow-icon {
		min-width: 32px;
		opacity: 0.66;
	}
}
</style>
