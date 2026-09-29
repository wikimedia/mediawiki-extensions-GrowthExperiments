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
			action: 'wbgetentities',
			ids: this.items.slice( 0, 50 ).map( ( item ) => item.title ),
			props: 'labels',
			languages: mw.config.get( 'wgUserLanguage' ),
			languagefallback: 1
		} ).then( ( response ) => {
			for ( const [ id, entity ] of Object.entries( response.entities || {} ) ) {
				const label = Object.values( entity.labels || {} )[ 0 ];
				if ( label ) {
					this.labels[ id ] = label.value;
				}
			}
		} ).catch( () => {
		} );
	}
};
</script>
