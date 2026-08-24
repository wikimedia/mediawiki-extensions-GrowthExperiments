<template>
	<div class="ext-growthExperiments-interest-selector">
		<cdx-field
			:status="status"
			:messages="validationMessages"
		>
			<cdx-multiselect-lookup
				v-model:input-chips="wrappedChips"
				v-model:selected="selection"
				v-model:input-value="inputValue"
				:separate-input="true"
				:menu-items="menuItems"
				:menu-config="menuConfig"
				:placeholder="$i18n( 'growthexperiments-interest-selector-placeholder' ).text()"
				:aria-label="$i18n( 'growthexperiments-interest-selector-a11y-label' ).text()"
				@update:input-value="onUpdateInputValueDebounced"
				@load-more="onLoadMore"
			>
				<template #no-results>
					{{ $i18n( 'growthexperiments-interest-selector-no-results-found' ).text() }}
				</template>
			</cdx-multiselect-lookup>
		</cdx-field>
		<section>
			<h2 class="ext-growthExperiments-interest-selector-related-articles">
				{{ $i18n( 'growthexperiments-interest-selector-related-articles-heading' ).text() }}
			</h2>
			<ul
				v-if="relatedArticles.length"
				class="ext-growthExperiments-interest-selector-related-articles-list"
			>
				<li
					v-for="article in relatedArticles"
					:key="article.value"
					class="ext-growthExperiments-interest-selector-related-article-list-item"
				>
					<button
						class="ext-growthExperiments-interest-selector-transparent-button"
						@click="() => addCardToSelectedChips( article )"
					>
						<cdx-card
							:force-thumbnail="true"
							:thumbnail="article.thumbnail"
						>
							<template #title>
								{{ article.label }}
							</template>
						</cdx-card>
					</button>
				</li>
			</ul>
			<cdx-progress-bar
				v-else
				inline
			></cdx-progress-bar>
		</section>
	</div>
</template>

<script>
const { defineComponent, ref, watch, toRef, inject, computed } = require( 'vue' );
const { CdxMultiselectLookup, CdxCard, CdxField, CdxProgressBar, useModelWrapper } = require( '@wikimedia/codex' );

/**
 * @import {Ref} from "vue"
 */

/**
 * A selected interest: a page name plus its display title. Narrower than Codex's
 * ChipInputItem (whose `value` is `string | number`), and assignable to it.
 *
 * @typedef {Object} InterestChip
 * @property {string} value Page name
 * @property {string} label Display title
 */

/**
 * @typedef {Object} RelatedArticleCard
 * @property {string} label
 * @property {string} value
 * @property {{url: string}|null} thumbnail
 */

/** @typedef {import('@wikimedia/codex').MenuItemData} MenuItemData */

// @vue/component
module.exports = exports = defineComponent( {
	name: 'InterestSelector',
	components: {
		CdxMultiselectLookup,
		CdxCard,
		CdxField,
		CdxProgressBar,
	},
	props: {
		/**
		 * Array of chips with shape { label: <display name>, value: <pagename>}
		 */
		// eslint-disable-next-line vue/no-unused-properties
		chips: {
			type: /** @type {import('vue').PropType<InterestChip[]>} */ ( Array ),
			default: () => [],
		},
	},
	emits: [ 'update:chips' ],
	setup( props, { emit } ) {
		const SOFT_MAX_NUMBER_OF_INTERESTS = 10;
		const MAX_NUMBER_OF_RELATED_ARTICLES = 5;
		const relatedArticleCache = new Map();
		/**
		 * @type {MwApi} mwApi
		 */
		const mwApi = inject( 'mwApi', () => new mw.Api(), true );

		const wrappedChips = useModelWrapper( toRef( props, 'chips' ), emit, 'update:chips' );

		const selection = ref( wrappedChips.value.map( ( chip ) => chip.value ) );

		const inputValue = ref( '' );

		/** @type Ref<MenuItemData[]> */
		const menuItems = ref( [] );

		/**
		 * @type Ref<RelatedArticleCard[]>
		 */
		const relatedArticles = ref( [] );

		const menuConfig = {
			boldLabel: true,
			visibleItemLimit: 6,
		};

		let fetchArticlesContinue = 0;
		/**
		 * Get search results.
		 *
		 * @param {string} searchTerm
		 * @param {boolean} [shouldContinue] Optional result offset
		 *
		 * @return {Promise<{title:string;description?:string}[]>}
		 */
		async function fetchResults( searchTerm, shouldContinue = false ) {
			/**
			 * @type {Record<string,string|number>}
			 */
			const params = {
				action: 'query',
				prop: 'description',
				generator: 'prefixsearch',
				gpslimit: '10',
				gpssearch: searchTerm,
			};
			if ( shouldContinue ) {
				params.gpsoffset = fetchArticlesContinue;
			} else {
				fetchArticlesContinue = 0;
			}
			const response = await mwApi.get( params );
			fetchArticlesContinue = response.continue && response.continue.gpsoffset ? response.continue.gpsoffset : 0;
			if ( !response.query || !response.query.pages ) {
				return [];
			}
			return Object.values( response.query.pages ).sort( ( a, b ) => a.index - b.index );
		}

		/**
		 * @param {string} pageName
		 * @return {Promise<Record<number,{title:string;thumbnail?:{source:string}}>>}
		 */
		async function fetchRelatedArticles( pageName ) {
			if ( relatedArticleCache.has( pageName ) ) {
				return relatedArticleCache.get( pageName );
			}
			const params = {
				action: 'query',
				prop: 'pageimages',
				generator: 'search',
				gsrlimit: '10',
				gsrsearch: `morelike:${ pageName }`,
			};
			const response = await mwApi.get( params );
			if ( !response.query || !response.query.pages ) {
				// cache intentionally not set, try again
				return {};
			}
			const relatedPages = response.query.pages;
			relatedArticleCache.set( pageName, relatedPages );
			return relatedPages;
		}

		/**
		 * @param {RelatedArticleCard} cardArticle
		 */
		function addCardToSelectedChips( cardArticle ) {
			wrappedChips.value = [ ...wrappedChips.value, { label: cardArticle.label, value: cardArticle.value } ];
			selection.value = [ ...selection.value, cardArticle.value ];
		}

		/**
		 * @param {string[]} pagenames
		 * @return {Promise<void>}
		 */
		async function updateRelatedArticlesFromSelectedPages( pagenames ) {
			if ( pagenames.length >= SOFT_MAX_NUMBER_OF_INTERESTS ) {
				relatedArticles.value = relatedArticles.value.filter( ( article ) => !pagenames.includes( article.label ) );
				return;
			}
			relatedArticles.value = [];
			if ( pagenames.length === 0 ) {
				return;
			}

			const relatedArticleDataResponses = await Promise.all(
				pagenames.map( fetchRelatedArticles ),
			);
			let relatedArticleData = Object.values( Object.assign( {}, ...relatedArticleDataResponses ) );

			relatedArticleData = relatedArticleData.filter( ( article ) => !pagenames.includes( article.title ) );
			relatedArticleData.sort( () => Math.random() - 0.5 );
			relatedArticles.value = relatedArticleData.slice( 0, MAX_NUMBER_OF_RELATED_ARTICLES ).map( ( article ) => (
				{
					label: article.title,
					value: article.title,
					thumbnail: article.thumbnail ? { url: article.thumbnail.source } : null,
				}
			) );
		}

		async function updateRelatedArticlesNoSeeds() {
			const data = await mwApi.get( {
				action: 'query',
				prop: 'pageimages',
				generator: 'search',
				gsrlimit: MAX_NUMBER_OF_RELATED_ARTICLES,
				gsrsearch: 'Wikipedia',
				gsrsort: 'random',
			} );

			if ( !data.query || !data.query.pages ) {
				relatedArticles.value = [];
				return;
			}

			const relatedArticleData = Object.values( data.query.pages );
			relatedArticles.value = relatedArticleData.map( ( article ) => (
				{
					label: article.title,
					value: article.title,
					thumbnail: article.thumbnail ? { url: article.thumbnail.source } : null,
				}
			) );
		}

		watch( wrappedChips, ( newChips ) => {
			if ( newChips.length === 0 ) {
				updateRelatedArticlesNoSeeds();
				return;
			}
			const pageNames = newChips.map( ( chip ) => chip.value );
			updateRelatedArticlesFromSelectedPages( pageNames );
		}, { immediate: true } );

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value The new input value
		 */
		function updateInputValue( value ) {
			// Clear menu items if the input was cleared.
			if ( !value ) {
				menuItems.value = [];
				return;
			}

			fetchResults( value )
				.then( ( pages ) => {
					// Make sure this data is still relevant first.
					if ( inputValue.value !== value ) {
						return;
					}

					// Reset the menu items if there are no results.
					if ( !pages || pages.length === 0 ) {
						menuItems.value = [];
						return;
					}

					menuItems.value = pages.map( ( page ) => ( {
						label: page.title,
						value: page.title,
						description: page.description,
					} ) );
				} )
				.catch( () => {
					menuItems.value = [];
				} );
		}

		const onUpdateInputValueDebounced = mw.util.debounce( updateInputValue, 300 );

		/**
		 * @param {MenuItemData[]} results
		 */
		function deduplicateMenuItems( results ) {
			const seen = new Set( menuItems.value.map( ( result ) => result.value ) );
			return results.filter( ( result ) => !seen.has( result.value ) );
		}

		function onLoadMore() {
			if ( !inputValue.value ) {
				return;
			}

			fetchResults( inputValue.value, true )
				.then( ( searchResults ) => {
					if ( searchResults.length === 0 ) {
						return;
					}

					const results = searchResults.map( ( result ) => ( {
						label: result.title,
						value: result.title,
						description: result.description,
					} ) );

					// Update menuItems.
					const deduplicatedResults = deduplicateMenuItems( results );
					menuItems.value.push( ...deduplicatedResults );
				} );
		}

		const status = computed( () => {
			if ( wrappedChips.value.length >= SOFT_MAX_NUMBER_OF_INTERESTS ) {
				return 'success';
			}
			return 'default';
		} );
		const i18n = inject( 'i18n' );
		const validationMessages = {
			success: i18n( 'growthexperiments-interest-selector-success' ).text(),
		};

		return {
			wrappedChips,
			status,
			validationMessages,
			selection,
			inputValue,
			menuItems,
			menuConfig,
			relatedArticles,
			onUpdateInputValueDebounced,
			onLoadMore,
			addCardToSelectedChips,
		};

	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-interest-selector {
	display: flex;
	flex-direction: column;
	gap: @spacing-150;

	.ext-growthExperiments-interest-selector {
		&-related-articles {
			color: @color-subtle;

			/* UI Text Bold */
			font-family: @font-family-base;
			font-size: @font-size-medium;
			font-style: normal;
			font-weight: bold;
			line-height: @line-height-small;
		}

		&-related-articles-list {
			margin: 0;
			padding: 0;
			display: flex;
			flex-direction: column;
			gap: @spacing-50;
			list-style: none;
		}

		&-related-article-list-item {
			margin: 0;
		}

		&-transparent-button {
			background: none;
			border: 0;
			padding: 0;
			width: 100%;
			text-align: inherit;
			font: inherit;
			cursor: pointer;
		}
	}
}
</style>
