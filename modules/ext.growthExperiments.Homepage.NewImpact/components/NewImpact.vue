<template>
	<section class="ext-growthExperiments-NewImpact">
		<!-- TODO: add skeletons, maybe use suspense, load sections only if data available -->
		<div v-if="data" class="ext-growthExperiments-NewImpact__scores">
			<score-card
				:icon="cdxIconEdit"
				:label="$i18n( 'growthexperiments-homepage-impact-scores-edit-count' )"
			>
				<c-link :href="contributionsUrl" :disable-visited="true">
					<c-text weight="bold">
						{{ totalEdits }}
					</c-text>
				</c-link>
			</score-card>
			<score-card
				:icon="cdxIconHeart"
				:label="$i18n( 'growthexperiments-homepage-impact-scores-thanks-count' )"
			>
				<c-text as="span" weight="bold">
					{{ data.receivedThanksCount }}
				</c-text>
			</score-card>
		</div>
	</section>
</template>

<script>
const ScoreCard = require( './ScoreCard.vue' );
const CText = require( '../../vue-components/CText.vue' );
const CLink = require( '../../vue-components/CLink.vue' );
const useMWRestApi = require( '../composables/useMWRestApi.js' );
const { cdxIconEdit, cdxIconHeart } = require( '../../vue-components/icons.json' );
const sum = ( arr ) => arr.reduce( ( x, y ) => x + y, 0 );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		ScoreCard,
		CText,
		CLink
	},
	props: {},
	setup() {
		const userId = mw.config.get( 'GENewImpactRelevantUserId' );
		const encodedUserId = encodeURIComponent( `#${userId}` );
		const { data, error } = useMWRestApi( `/growthexperiments/v0/user-impact/${encodedUserId}` );
		return {
			cdxIconEdit,
			cdxIconHeart,
			data,
			// TODO: how to give user error feedback?
			// eslint-disable-next-line vue/no-unused-properties
			error
		};
	},
	computed: {
		contributionsUrl() {
			return mw.util.getUrl( `Special:Contributions/${this.userName}` );
		},
		totalEdits() {
			const edits = Object.keys( this.data.editCountByNamespace )
				.map( ( k ) => this.data.editCountByNamespace[ k ] );
			return sum( edits );
		},
		userName() {
			return mw.config.get( 'GENewImpactRelevantUserName' );
		}
	}
};
</script>

<style lang="less">
.ext-growthExperiments-NewImpact {
	&__scores {
		display: grid;
		grid-template-columns: 1fr 1fr;
		grid-gap: 2px;
		// Expand scores stripe over homepage modules padding
		margin: 0 -16px;
	}
}
</style>
