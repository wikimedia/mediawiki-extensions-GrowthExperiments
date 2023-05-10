<template>
	<div class="ext-growthExperiments-PersonalizedPraise-Pagination">
		<cdx-button
			weight="quiet"
			:disabled="previousButtonDisabled"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-page-previous-icon-label' )"
			@click="$emit( 'previous' )"
		>
			<cdx-icon :icon="cdxIconPrevious"></cdx-icon>
		</cdx-button>
		<c-text color="subtle" class="ext-growthExperiments-PersonalizedPraise-Pagination__label">
			{{ $i18n(
				'growthexperiments-mentor-dashboard-personalized-praise-page-counter',
				$filters.convertNumber( currentPage ),
				$filters.convertNumber( totalPages )
			) }}
		</c-text>
		<cdx-button
			weight="quiet"
			:disabled="nextButtonDisabled"
			:aria-label="$i18n( 'growthexperiments-mentor-dashboard-personalized-praise-page-next-icon-label' )"
			@click="$emit( 'next' )"
		>
			<cdx-icon :icon="cdxIconNext"></cdx-icon>
		</cdx-button>
	</div>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconPrevious, cdxIconNext } = require( '../../../vue-components/icons.json' );
const CText = require( '../../../vue-components/CText.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon,
		CText
	},
	props: {
		currentPage: { type: Number, required: true },
		totalPages: { type: Number, required: true }
	},
	emits: [ 'previous', 'next' ],
	setup() {
		return {
			cdxIconPrevious,
			cdxIconNext
		};
	},
	computed: {
		previousButtonDisabled() {
			return this.currentPage <= 1;
		},
		nextButtonDisabled() {
			return this.currentPage >= this.totalPages;
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-PersonalizedPraise {
	&-Pagination {
		display: flex;

		&__label {
			width: 100%;
			text-align: center;
			margin: auto 0;
		}
	}
}
</style>
