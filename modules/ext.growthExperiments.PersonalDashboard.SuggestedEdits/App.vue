<template>
	<div class="growthexperiments-suggested-edits">
		<feed-panel
			v-bind="{ ...$attrs, ...feedState }"
			module-name="ext.growthExperiments.PersonalDashboard.SuggestedEdits"
			:is-narrow="isNarrow"
			summary-mode="card"
			footer-id="growthexperiments-suggested-edits-see-all"
			:footer-label="footerLabel"
			:progress-bar-aria-label="progressBarAriaLabel">
			<template #item="{ item }">
				<task-card
					:task="item"
					:position="feedState.items.indexOf( item )"></task-card>
			</template>
		</feed-panel>

		<!-- The scaffold renders nothing for an empty feed, and a card whose body
			is blank reads as broken rather than as "nothing to suggest today". -->
		<p v-if="isEmpty">
			{{ noResultsLabel }}
		</p>
	</div>

	<!-- The menu button belongs in the module header, which the server renders.
		The header slot is the client's mount point there. This teleport nests
		inside the island's own teleport, and follows the body into the dialog or
		the in-page frame, since either stands in for the whole card. -->
	<teleport v-if="headerTarget" :to="headerTarget">
		<module-header-menu
			v-slot="{ anchor }"
			:menu-items="menuItems"
			:footer-item="menuFooterItem"
			:button-label="menuButtonLabel"
			@select="openPanel = $event">
			<module-panel
				:open="openPanel === 'about'"
				:anchor="anchor"
				:is-narrow="isNarrow"
				:title="aboutTitle"
				@update:open="closePanel">
				<p>{{ aboutBody }}</p>
			</module-panel>
			<task-types-dialog
				:open="openPanel === 'taskTypes'"
				@update:open="closePanel"
				@saved="reload"></task-types-dialog>
		</module-header-menu>
	</teleport>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const {
	FeedPanel,
	FULL_LIMIT,
	ModuleHeaderMenu,
	ModulePanel,
} = require( 'ext.personalDashboard.common' );
const TaskCard = require( './TaskCard.vue' );
const TaskTypesDialog = require( './TaskTypesDialog.vue' );
const { useSuggestedEditsFeed } = require( './useSuggestedEditsFeed.js' );
const { cdxIconChartBar, cdxIconConfigure } = require( './icons.json' );

module.exports = defineComponent( {
	components: {
		FeedPanel,
		ModuleHeaderMenu,
		ModulePanel,
		TaskCard,
		TaskTypesDialog,
	},
	// The compact/full island props (detail, focused, active) are never declared
	// here: they ride in $attrs and are forwarded untouched to the scaffold,
	// which owns the compact/full derivation. This module only decides which
	// rule it follows, via summary-mode, and hands over the feed contract.
	inheritAttrs: false,
	props: {
		// Selector for the mount slot in the server-rendered module header, or
		// null when the frame emits none. IslandMount resolves it. Declared
		// rather than left to ride in $attrs because this module consumes it
		// itself; the scaffold has no use for it.
		headerTarget: {
			type: String,
			default: null,
		},
		// Declared, unlike its fellow island props, because the about panel
		// switches on it: a dialog on a wide viewport, a bottom sheet on a narrow
		// one. Declaring it takes it out of $attrs, so the template hands it to
		// the scaffold by name instead.
		isNarrow: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		const { feedState, load } = useSuggestedEditsFeed();

		// Which panel the menu has opened, or null for none. One value rather
		// than a flag each: the menu opens one panel at a time.
		const openPanel = ref( null );

		// Here rather than in mounted(): load() raises the loading flag before it
		// awaits anything, and mounted() runs after the first render, so deferring
		// it paints the empty-state line for a frame on every mount.
		load( FULL_LIMIT );

		return {
			feedState,
			openPanel,
			// A panel only ever reports itself closed: it is opened from the menu
			// above, never by its own model.
			closePanel: () => {
				openPanel.value = null;
			},
			reload: () => load( FULL_LIMIT ),
			/*
			 * The interests item does not open anything yet. The interest picker,
			 * though it is Vue, is written against the whole Codex library rather
			 * than the tree-shaken subset an island gets. Reusing it is its own
			 * task (T439432); the item is here so the menu reads as designed.
			 */
			menuItems: [
				{
					value: 'interests',
					label: mw.msg( 'growthexperiments-homepage-suggestededits-menu-interests' ),
					icon: cdxIconConfigure,
				},
				{
					value: 'taskTypes',
					label: mw.msg( 'growthexperiments-homepage-suggestededits-menu-tasktypes' ),
					icon: cdxIconChartBar,
				},
			],
			// The footer, not a third ordinary item: design shows a divider above
			// it, and that is what Codex gives a menu footer.
			menuFooterItem: {
				value: 'about',
				label: mw.msg( 'growthexperiments-homepage-suggestededits-menu-about' ),
			},
			footerLabel: mw.msg( 'growthexperiments-homepage-suggestededits-mobilesummary-footer-button' ),
			noResultsLabel: mw.msg( 'growthexperiments-homepage-suggestededits-no-results' ),
			progressBarAriaLabel: mw.msg( 'growthexperiments-homepage-suggestededits-progress-bar-aria-label' ),
			menuButtonLabel: mw.msg( 'growthexperiments-homepage-suggestededits-menu-button-label' ),
			// The menu item names the panel it opens, so both read one message.
			aboutTitle: mw.msg( 'growthexperiments-homepage-suggestededits-menu-about' ),
			aboutBody: mw.msg( 'growthexperiments-homepage-suggestededits-about-body' ),
		};
	},
	computed: {
		isEmpty() {
			return !this.feedState.isLoading &&
				!this.feedState.error &&
				!this.feedState.items.length;
		},
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-module-SuggestedEdits .personal-dashboard-module-section-body {
	margin: @spacing-0;
}
</style>
