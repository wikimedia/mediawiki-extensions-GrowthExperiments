<template>
	<div class="ext-growthExperiments-ArticlesList">
		<c-list unstyled striped>
			<c-list-item v-for="( article, index ) in items" :key="article.title">
				<articles-list-item :article="article" :index="index">
				</articles-list-item>
			</c-list-item>
		</c-list>
	</div>
</template>

<script>
const ArticlesListItem = require( './ArticlesListItem.vue' );
const CList = require( '../../vue-components/CList.vue' );
const CListItem = require( '../../vue-components/CListItem.vue' );

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		ArticlesListItem,
		CList,
		CListItem
	},
	props: {
		items: {
			type: Array,
			default: () => ( [] )
		}
	}
};
</script>
