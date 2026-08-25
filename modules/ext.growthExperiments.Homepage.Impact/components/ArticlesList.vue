<template>
	<div class="ext-growthExperiments-ArticlesList">
		<c-list unstyled striped>
			<c-list-item v-for="( article, index ) in items" :key="article.title">
				<articles-list-item
					:article="article"
					:index="index"
					:label="labels[ article.title ]"
				>
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
	},
	data() {
		return {
			labels: {}
		};
	},
	mounted() {
		if ( !mw.config.get( 'GEImpactUseWikibaseLabels' ) || !this.items.length ) {
			return;
		}

		new mw.Api().get( {
			action: 'query',
			prop: 'pageterms',
			titles: this.items.slice( 0, 50 ).map( ( item ) => item.title ),
			wbptterms: 'label',
			wbptlanguage: mw.config.get( 'wgUserLanguage' ),
			formatversion: 2
		} ).then( ( response ) => {
			for ( const page of response.query.pages || [] ) {
				if ( page.terms && page.terms.label ) {
					this.labels[ page.title ] = page.terms.label[ 0 ];
				}
			}
		} ).catch( () => {
		} );
	}
};
</script>
