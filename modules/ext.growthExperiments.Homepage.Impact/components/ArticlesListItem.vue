<template>
	<div class="ext-growthExperiments-ArticleListItem">
		<a
			class="ext-growthExperiments-ArticleListItem__info"
			:title="$i18n( 'growthexperiments-homepage-impact-article-link-tooltip' ).text()"
			:href="article.href"
			:data-link-data="article.views.count"
			data-link-id="impact-article-title"
		>
			<cdx-thumbnail :thumbnail="{ url: article.image.href }">
			</cdx-thumbnail>
			<c-text
				as="span"
				weight="bold"
				class="ext-growthExperiments-ArticleListItem__info__title"
			>
				{{ article.title }}
			</c-text>
		</a>
		<div class="ext-growthExperiments-ArticleListItem__pageviews">
			<a
				v-if="Number.isInteger( article.views.count )"
				class="ext-growthExperiments-ArticleListItem__pageviews__link"
				:title="$i18n( 'growthexperiments-homepage-impact-pageviews-link-tooltip' ).text()"
				:href="article.views.href"
				:data-link-data="article.views.count"
				data-link-id="impact-pageviews"
			>
				<c-text
					weight="bold"
					class="ext-growthExperiments-ArticleListItem__pageviews__count"
				>
					{{ $filters.convertNumber( article.views.count ) }}
				</c-text>
				<c-sparkline
					:id="`article-${index}`"
					class="ext-growthExperiments-ArticleListItem__pageviews__sparkline"
					:title="$i18n( 'growthexperiments-homepage-impact-pageviews-link-tooltip' ).text()"
					:data="article.views.entries"
					:dimensions="{ width: 20, height: 20 }"
					:x-accessor="xAccessor"
					:y-accessor="yAccessor"
					with-circle
				></c-sparkline>
			</a>
			<div
				v-else
				class="ext-growthExperiments-ArticleListItem__pageviews__clock"
			>
				<cdx-toggle-button
					ref="clockToggleButton"
					v-model="showPopover"
					:aria-label="$i18n( 'growthexperiments-homepage-impact-empty-pageviews-tooltip-short' ).text()"
					:quiet="true"
					class="ext-growthExperiments-ArticleListItem__clock-button"
				>
					<cdx-icon :icon="cdxIconClock"></cdx-icon>
				</cdx-toggle-button>
				<cdx-popover
					v-model:open="showPopover"
					:anchor="windowName !== 'ArticlesListItemVueTests' ? clockToggleButton : null"
					placement="top-start"
					:render-in-place="true"
					@update:open="onPopoverToggleChange"
				>
					<c-text
						class="ext-growthExperiments-ArticleListItem__tooltip-text"
					>
						{{ $i18n( 'growthexperiments-homepage-impact-empty-pageviews-tooltip-short' ).text() }}
					</c-text>
				</cdx-popover>
			</div>
		</div>
	</div>
</template>

<script>
const { computed, ref, inject } = require( 'vue' );
const {
	CdxIcon,
	CdxThumbnail,
	CdxPopover,
	CdxToggleButton
} = require( '@wikimedia/codex' );
const { cdxIconClock } = require( '../../vue-components/icons.json' );
const CText = require( '../../vue-components/CText.vue' );
const CSparkline = require( '../../vue-components/CSparkline.vue' );
const xAccessor = ( d ) => d.date;
const yAccessor = ( d ) => d.views;

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxIcon,
		CdxPopover,
		CdxToggleButton,
		CdxThumbnail,
		CText,
		CSparkline
	},
	props: {
		index: {
			type: Number,
			required: true
		},
		article: {
			type: Object,
			required: true
		}
	},
	setup() {
		const clockToggleButton = ref( null );
		const showPopover = ref( false );
		const logger = inject( 'logger' );
		const windowName = computed( () => window.name );
		const onPopoverToggleChange = ( value ) => {
			const action = value ? 'open-nopageviews-tooltip' : 'close-nopageviews-tooltip';
			logger.log( 'impact', action );
		};
		return {
			cdxIconClock,
			clockToggleButton,
			onPopoverToggleChange,
			showPopover,
			xAccessor,
			yAccessor,
			windowName
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import '../../utils/mixins.less';

.ext-growthExperiments-ArticleListItem {
	display: inline-flex;
	align-items: center;
	width: 100%;
	color: @color-base;

	> * {
		padding: @spacing-25;
	}

	&__info {
		flex: 5;
		min-width: 0;
		display: flex;
		align-items: center;

		a& {
			.disabled-visited();

			&:hover {
				text-decoration: none;
			}
		}

		&__title {
			margin-left: 0.5em;
			.paragraph-ellipsis( @lines: 2, @parentFontSize: 14px, @targetFontSize: 14px, @targetLineHeight: 22.4px );
		}
	}

	&__pageviews {
		&__clock {
			position: relative;
		}

		&__count {
			color: @color-progressive;
		}

		&__link {
			display: inline-flex;
			align-items: baseline;
		}

		&__sparkline {
			margin-left: 6px;
			width: 20px;
			height: 20px;
		}
	}

	&__clock-button {
		.codex-icon-only-button( @color-progressive );
	}

	&__tooltip-text {
		min-width: 220px;
	}
}
</style>
