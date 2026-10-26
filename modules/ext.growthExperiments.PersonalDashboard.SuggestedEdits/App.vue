<template>
	<div class="growthexperiments-suggested-edits">
		<interests-notice
			v-if="showInterestsNotice"
			@select-interests="openPanel = 'interests'"
			@dismiss="onInterestsNoticeDismissed"></interests-notice>

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
			<about-panel
				:open="openPanel === 'about'"
				:anchor="anchor"
				:is-narrow="isNarrow"
				@update:open="closePanel"></about-panel>
			<task-types-dialog
				:open="openPanel === 'taskTypes'"
				@update:open="closePanel"
				@saved="onTaskTypesSaved"></task-types-dialog>
			<interest-selector-dialog
				:stacked-actions="true"
				:open="openPanel === 'interests'"
				@update:open="closePanel"
				@saved="onInterestsSaved"></interest-selector-dialog>
		</module-header-menu>
	</teleport>
</template>

<script>
const { computed, defineComponent, nextTick, ref } = require( 'vue' );
const {
	FeedPanel,
	FULL_LIMIT,
	ModuleHeaderMenu,
} = require( 'ext.personalDashboard.common' );
const AboutPanel = require( './AboutPanel.vue' );
const TaskCard = require( './TaskCard.vue' );
const TaskTypesDialog = require( './TaskTypesDialog.vue' );
const InterestSelectorDialog = require( './InterestSelectorDialog.vue' );
const InterestsNotice = require( './InterestsNotice.vue' );
const { newcomerTasks } = require( 'ext.growthExperiments.DataStore' );
const { useSuggestedEditsFeed } = require( './useSuggestedEditsFeed.js' );
const { cdxIconChartBar, cdxIconConfigure } = require( './icons.json' );

/** Keep in sync with HomeOnboardingActionLookup::INTERESTS_NOTICE_INTERACTED_PREF. */
const INTERESTS_NOTICE_INTERACTED_PREF = 'growthexperiments-home-interests-notice-interacted';
/**
 * The HomeOnboardingActionLookup::ACTION_* values that show the notice. The
 * welcome back dialog shows on top of it, and the notice stays when the user
 * closes the dialog.
 */
const INTERESTS_NOTICE_ACTIONS = [ 'show-no-interests-notice', 'show-welcome-back' ];

module.exports = exports = defineComponent( {
	components: {
		AboutPanel,
		FeedPanel,
		InterestSelectorDialog,
		InterestsNotice,
		ModuleHeaderMenu,
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
	setup( props ) {
		const { feedState, load } = useSuggestedEditsFeed();

		// Which panel the menu has opened, or null for none. One value rather
		// than a flag each: the menu opens one panel at a time.
		const openPanel = ref( null );

		const selectedTaskTypeCount = ref( newcomerTasks.filters.getSelectedTaskTypes().length );

		const selectedInterestCount = ref( newcomerTasks.filters.getSelectedInterests().length );

		// Only Special:Home sets the action, so other dashboards never show the notice.
		const showInterestsNotice = ref(
			INTERESTS_NOTICE_ACTIONS.includes( mw.config.get( 'wgGEHomeOnboardingAction' ) ),
		);

		// The notice holds the focus when it goes, or it gave the focus to the
		// interest selector, which gives the focus back to it on close. Without
		// the notice the focus goes to <body>. Move the focus to the module menu,
		// because the user selects interests there from now on.
		const hideInterestsNotice = () => {
			showInterestsNotice.value = false;
			nextTick( () => {
				const header = props.headerTarget && document.querySelector( props.headerTarget );
				const menuButton = header && header.querySelector(
					'.personal-dashboard-module-menu__button > .cdx-button',
				);
				if ( menuButton ) {
					menuButton.focus();
				}
			} );
		};

		const onInterestsNoticeDismissed = () => {
			hideInterestsNotice();
			new mw.Api().saveOption( INTERESTS_NOTICE_INTERACTED_PREF, 1 ).catch( ( error ) => {
				mw.errorLogger.logError(
					new Error( 'Unable to save the interests notice dismissal: ' + error ),
					'error.growthexperiments',
				);
			} );
		};

		// Here rather than in mounted(): load() raises the loading flag before it
		// awaits anything, and mounted() runs after the first render, so deferring
		// it paints the empty-state line for a frame on every mount.
		load( FULL_LIMIT );

		return {
			feedState,
			openPanel,
			showInterestsNotice,
			// A panel only ever reports itself closed: it is opened from the menu
			// above or from the interests notice, never by its own model.
			closePanel: () => {
				const interestsClosed = openPanel.value === 'interests';
				openPanel.value = null;
				// When the user leaves the interest selector with no interests, the
				// notice is dismissed. It does not matter if the user opened the
				// selector from the notice or from the menu. Every exit of the
				// dialog counts: "Done", "Cancel", the close button and Esc. The
				// dialog updates the store before it closes, so the store holds the
				// result of "Done" here. Cancelled selections are discarded, so they
				// also leave the store empty.
				if (
					interestsClosed &&
					showInterestsNotice.value &&
					!newcomerTasks.filters.getSelectedInterests().length
				) {
					onInterestsNoticeDismissed();
				}
			},
			onTaskTypesSaved: () => {
				selectedTaskTypeCount.value = newcomerTasks.filters.getSelectedTaskTypes().length;
				load( FULL_LIMIT );
			},
			// The dialog saves the interests, and the API reads them for itself, so the
			// feed only has to ask again.
			onInterestsSaved: () => {
				selectedInterestCount.value = newcomerTasks.filters.getSelectedInterests().length;
				// No preference to save: with interests stored, the server does not
				// ask for the notice again.
				if ( selectedInterestCount.value > 0 && showInterestsNotice.value ) {
					hideInterestsNotice();
				}
				load( FULL_LIMIT );
			},
			onInterestsNoticeDismissed,
			menuItems: computed( () => [
				{
					value: 'interests',
					label: mw.msg( 'growthexperiments-home-suggestededits-menu-interests' ),
					description: mw.message(
						'growthexperiments-home-suggestededits-menu-interests-selected',
						mw.language.convertNumber( selectedInterestCount.value ),
					).text(),
					icon: cdxIconConfigure,
				},
				{
					value: 'taskTypes',
					label: mw.msg( 'growthexperiments-home-suggestededits-menu-tasktypes' ),
					description: mw.message(
						'growthexperiments-home-suggestededits-menu-tasktypes-selected',
						mw.language.convertNumber( selectedTaskTypeCount.value ),
					).text(),
					icon: cdxIconChartBar,
				},
			] ),
			// The footer, not a third ordinary item: design shows a divider above
			// it, and that is what Codex gives a menu footer.
			menuFooterItem: {
				value: 'about',
				label: mw.msg( 'growthexperiments-home-suggestededits-menu-about' ),
			},
			footerLabel: mw.msg( 'growthexperiments-homepage-suggestededits-mobilesummary-footer-button' ),
			noResultsLabel: mw.msg( 'growthexperiments-homepage-suggestededits-no-results' ),
			progressBarAriaLabel: mw.msg( 'growthexperiments-home-suggestededits-progress-bar-aria-label' ),
			menuButtonLabel: mw.msg( 'growthexperiments-home-suggestededits-menu-button-label' ),
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
