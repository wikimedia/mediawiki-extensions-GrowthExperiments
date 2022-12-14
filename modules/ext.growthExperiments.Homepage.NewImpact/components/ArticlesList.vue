<template>
	<div class="ext-growthExperiments-ArticlesList">
		<c-list unstyled striped>
			<c-list-item v-for="( article, index ) in items" :key="article.title">
				<div class="ext-growthExperiments-ArticlesList__ArticleListItem">
					<a
						class="ext-growthExperiments-ArticlesList__ArticleListItem__info"
						:title="$i18n( 'growthexperiments-homepage-impact-article-link-tooltip' ).text()"
						:href="article.href"
						data-link-id="impact-article-title"
					>
						<cdx-thumbnail :thumbnail="{ url: article.image.href }">
						</cdx-thumbnail>
						<c-text
							as="span"
							weight="bold"
							class="ext-growthExperiments-ArticlesList__ArticleListItem__info__title"
						>
							{{ article.title }}
						</c-text>
					</a>
					<div class="ext-growthExperiments-ArticlesList__ArticleListItem__pageviews">
						<a
							v-if="Number.isInteger( article.views.count )"
							class="ext-growthExperiments-ArticlesList__ArticleListItem__pageviews__link"
							:title="$i18n( 'growthexperiments-homepage-impact-pageviews-link-tooltip' ).text()"
							:href="article.views.href"
							:data-link-data="article.views.count"
							data-link-id="impact-pageviews"
						>
							<c-text
								weight="bold"
								class="ext-growthExperiments-ArticlesList__ArticleListItem__pageviews__count"
							>
								{{ $filters.convertNumber( article.views.count ) }}
							</c-text>
							<c-sparkline
								:id="`article-${index}`"
								class="ext-growthExperiments-ArticlesList__ArticleListItem__pageviews__sparkline"
								:title="$i18n( 'growthexperiments-homepage-impact-pageviews-link-tooltip' ).text()"
								:data="article.views.entries"
								:dimensions="{ width: 20, height: 20 }"
								:x-accessor="xAccessor"
								:y-accessor="yAccessor"
							></c-sparkline>
						</a>
						<c-popover
							v-else
							placement="above"
							@open="log( 'impact', 'open-nopageviews-tooltip' )"
							@close="log( 'impact', 'close-nopageviews-tooltip' )"
						>
							<template #trigger="{ onClick }">
								<cdx-button
									class="ext-growthExperiments-ArticlesList__ArticleListItem__clock-button"
									type="quiet"
									:aria-label="$i18n( 'growthexperiments-homepage-impact-empty-pageviews-tooltip-short' ).text()"
									@click="onClick"
								>
									<cdx-icon :icon="cdxIconClock"></cdx-icon>
								</cdx-button>
							</template>
							<template #content>
								<c-text
									class="ext-growthExperiments-ArticlesList__ArticleListItem__tooltip-text"
								>
									{{ $i18n( 'growthexperiments-homepage-impact-empty-pageviews-tooltip-short' ).text() }}
								</c-text>
							</template>
						</c-popover>
					</div>
				</div>
			</c-list-item>
		</c-list>
	</div>
</template>

<script>
const { inject, defineAsyncComponent } = require( 'vue' );
const CList = require( '../../vue-components/CList.vue' );
const CListItem = require( '../../vue-components/CListItem.vue' );
const CPopover = require( '../../vue-components/CPopover.vue' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const CText = require( '../../vue-components/CText.vue' );
const { CdxThumbnail } = require( '@wikimedia/codex' );
const { cdxIconClock } = require( '../../vue-components/icons.json' );
const xAccessor = ( d ) => d.date;
const yAccessor = ( d ) => d.views;

const CSparkline = defineAsyncComponent( () => {
	if ( mw.config.get( 'GENewImpactD3Enabled' ) ) {
		return mw.loader.using( 'ext.growthExperiments.d3' )
			.then( () => require( '../../vue-components/CSparkline.vue' ) );
	} else {
		// Maybe fallback to a static image
		return Promise.resolve( null );
	}
} );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon,
		CdxButton,
		CPopover,
		CdxThumbnail,
		CList,
		CListItem,
		CText,
		CSparkline
	},
	props: {
		items: {
			type: Array,
			default: () => ( [] )
		}
	},
	setup() {
		const log = inject( '$log' );
		return {
			log,
			cdxIconClock,
			xAccessor,
			yAccessor
		};
	}
};
</script>

<style lang="less">
@import '../../vue-components/variables.less';
@import '../../utils/mixins.less';

.ext-growthExperiments-ArticlesList {
	&__ArticleListItem {
		display: inline-flex;
		align-items: center;
		width: 100%;
		color: @color-base;

		> * {
			padding: @padding-vertical-base;
		}

		&__info {
			flex: 5;
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
			padding-top: @padding-vertical-base;
			min-width: 220px;
		}
	}
}
</style>
