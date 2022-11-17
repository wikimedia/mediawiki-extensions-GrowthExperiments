<template>
	<div class="ext-growthExperiments-ArticlesList">
		<c-list unstyled striped>
			<c-list-item v-for="( article, index ) in items" :key="article.title">
				<div class="ext-growthExperiments-ArticlesList__ArticleListItem">
					<a
						class="ext-growthExperiments-ArticlesList__ArticleListItem__info"
						:title="$i18n( 'growthexperiments-homepage-impact-article-link-tooltip' ).text()"
						:href="article.href"
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
						<cdx-icon
							v-else
							class="ext-growthExperiments-ArticlesList__ArticleListItem__clock-icon"
							:title="$i18n( 'growthexperiments-homepage-impact-empty-pageviews-tooltip-short' )"
							:icon="cdxIconClock"
						></cdx-icon>
					</div>
				</div>
			</c-list-item>
		</c-list>
	</div>
</template>

<script>
const { defineAsyncComponent } = require( 'vue' );
const CList = require( '../../vue-components/CList.vue' );
const CListItem = require( '../../vue-components/CListItem.vue' );
const CText = require( '../../vue-components/CText.vue' );
const { CdxIcon, CdxThumbnail } = require( '@wikimedia/codex' );
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
		return {
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

			&:visited {
				color: inherit;
			}

			&:hover {
				text-decoration: none;
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

		&__clock-icon {
			color: @color-progressive;
		}
	}
}
</style>
