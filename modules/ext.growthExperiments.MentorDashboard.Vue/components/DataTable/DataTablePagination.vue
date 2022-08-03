<template>
	<div class="ext-growthExperiments-DataTablePagination">
		<span>
			{{ paginationText }}
		</span>
		<cdx-button
			type="quiet"
			:disabled="currentPage === 1"
			@click="$emit( 'prev', $event )"
		>
			<cdx-icon
				class="ext-growthExperiments-DataTablePagination__arrow-icon"
				:icon="cdxIconPrevious"
				:icon-label="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-page-prev-icon-label' )"
			></cdx-icon>
		</cdx-button>
		<cdx-button
			type="quiet"
			:disabled="currentPage === totalPages"
			@click="$emit( 'next', $event )"
		>
			<cdx-icon
				class="ext-growthExperiments-DataTablePagination__arrow-icon"
				:icon="cdxIconNext"
				:icon-label="$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-page-next-icon-label' )"
			></cdx-icon>
		</cdx-button>
	</div>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconNext, cdxIconPrevious } = require( '../icons.json' );

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
				mw.language.convertNumber( this.currentPage ),
				mw.language.convertNumber( this.totalPages )
			);
		}
	}
};
</script>

<style lang="less">
@import '../variables.less';

.ext-growthExperiments-DataTablePagination {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: @padding-base;

	&__arrow-icon {
		min-width: 32px;
		opacity: 0.66;
	}
}
</style>
