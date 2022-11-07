<template>
	<div class="ext-growthExperiments-ArticlesList">
		<c-list unstyled striped>
			<c-list-item v-for="( article, index ) in items" :key="article.title">
				<div class="ext-growthExperiments-ArticlesList__ArticleListItem">
					<a class="ext-growthExperiments-ArticlesList__ArticleListItem__info" :href="article.href">
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
						<c-text
							weight="bold"
							class="ext-growthExperiments-ArticlesList__ArticleListItem__pageviews__count"
						>
							<span v-if="Number.isInteger( article.views.count )">
								{{ $filters.convertNumber( article.views.count ) }}
							</span>
							<a v-else>
								<cdx-icon
									class="ext-growthExperiments-ArticlesList__ArticleListItem__clock-icon"
									:icon="cdxIconClock"
								></cdx-icon>
							</a>
						</c-text>
						<a
							:href="article.views.href"
							class="ext-growthExperiments-ArticlesList__ArticleListItem__pageviews__link"
						>
							<c-sparkline
								:id="`article-${index}`"
								class="ext-growthExperiments-ArticlesList__ArticleListItem__sparkline"
								:data="article.views.entries"
								:dimensions="{ width: 16, height: 12 }"
								:x-accessor="xAccessor"
								:y-accessor="yAccessor"
							></c-sparkline>
						</a>
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
			display: inline-flex;
			align-items: baseline;

			&__count {
				color: @color-progressive;
			}

			&__link {
				margin-left: 6px;
			}
		}

		&__clock-icon {
			color: @color-progressive;
		}

		&__sparkline {
			width: 16px;
			height: 12px;
		}
	}
}
</style>