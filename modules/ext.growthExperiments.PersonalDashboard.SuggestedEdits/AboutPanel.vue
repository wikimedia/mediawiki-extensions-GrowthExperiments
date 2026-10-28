<template>
	<module-panel
		:open="open"
		:anchor="anchor"
		:is-narrow="isNarrow"
		:title="title"
		@update:open="$emit( 'update:open', $event )">
		<p>{{ intro }}</p>
		<p>{{ purpose }}</p>
		<p
			v-i18n-html:growthexperiments-home-suggestededits-about-recommendations="[ recommendationsUrl ]"
			class="plainlinks"></p>
	</module-panel>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { ModulePanel } = require( 'ext.personalDashboard.common' );
const { GEHomepageSuggestedEditsRecommendationsUrl } = require( './config.json' );

/**
 * What suggested edits are, and how the wiki recommends them. The last
 * paragraph links to the recommender system documentation, which the
 * recommender system compliance checklist asks us to show (T436863).
 */
module.exports = exports = defineComponent( {
	name: 'AboutPanel',
	components: { ModulePanel },
	props: {
		open: {
			type: Boolean,
			default: false,
		},
		anchor: {
			type: Object,
			default: null,
		},
		isNarrow: {
			type: Boolean,
			default: false,
		},
	},
	emits: [ 'update:open' ],
	setup() {
		return {
			// The menu item names the panel it opens, so both read one message.
			title: mw.msg( 'growthexperiments-home-suggestededits-menu-about' ),
			intro: mw.msg( 'growthexperiments-home-suggestededits-about-intro' ),
			purpose: mw.msg( 'growthexperiments-home-suggestededits-about-purpose' ),
			// The wiki sets where the documentation is. The link markup stays in
			// the message, so a translation can put it on a different word.
			recommendationsUrl: GEHomepageSuggestedEditsRecommendationsUrl,
		};
	},
} );
</script>
