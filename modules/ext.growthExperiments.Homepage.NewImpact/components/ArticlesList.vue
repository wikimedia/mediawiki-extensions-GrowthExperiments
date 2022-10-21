<template>
	<div class="ext-growthExperiments-ArticlesList">
		<c-list unstyled striped>
			<c-list-item v-for="article in items" v-bind:key="article.title">
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
						<c-text weight="bold">
							<a v-if="Number.isInteger( article.views.count )" :href="article.views.href">
								{{ $filters.convertNumber( article.views.count ) }}
							</a>
							<a v-else>
								<cdx-icon
									class="ext-growthExperiments-ArticlesList__ArticleListItem__clock-icon"
									:icon="cdxIconClock"
								></cdx-icon>
							</a>
						</c-text>
					</div>
				</div>
			</c-list-item>
		</c-list>
	</div>
</template>

<script>
const CList = require( '../../vue-components/CList.vue' );
const CListItem = require( '../../vue-components/CListItem.vue' );
const CText = require( '../../vue-components/CText.vue' );
const { CdxIcon, CdxThumbnail } = require( '@wikimedia/codex' );
const { cdxIconClock } = require( '../../vue-components/icons.json' );
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxIcon,
		CdxThumbnail,
		CList,
		CListItem,
		CText
	},
	props: {
		items: {
			type: Array,
			default: () => ( [] )
		}
	},
	setup() {
		return {
			cdxIconClock
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

		> * {
			padding: @padding-vertical-base;
		}

		&__info {
			flex: 5;
			display: flex;
			align-items: center;

			&:visited {
				color: none;
			}

			&:hover {
				text-decoration: none;
			}

			&__title {
				margin-left: 0.5em;
				.paragraph-ellipsis( @lines: 2, @parentFontSize: 14px, @targetFontSize: 14px, @targetLineHeight: 22.4px );
			}
		}

		&__clock-icon {
			color: @color-progressive;
		}
	}
}
</style>
