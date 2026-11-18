<template>
	<div class="growthexperiments-suggested-edits">
		<no-match-notice
			v-if="showNoMatchNotice"
			ref="noMatchNotice"
			@select-interests="openPanel = 'interests'"
			@select-task-types="openPanel = 'taskTypes'"
			@dismiss="onNoMatchNoticeDismissed"></no-match-notice>

		<!-- The no match notice also tells the user to select interests. -->
		<interests-notice
			v-if="showInterestsNotice && !showNoMatchNotice"
			@select-interests="openPanel = 'interests'"
			@dismiss="onInterestsNoticeDismissed"></interests-notice>

		<welcome-back-dialog
			v-model:open="showWelcomeBack"
			@select-interests="openInterestsFromWelcomeBack"></welcome-back-dialog>

		<quality-gate-dialog
			v-model:open="showQualityGate"
			:task-type="qualityGateTaskType"></quality-gate-dialog>

		<feed-panel
			v-bind="{ ...$attrs, ...feedState }"
			module-name="ext.growthExperiments.PersonalDashboard.SuggestedEdits"
			:is-narrow="isNarrow"
			summary-mode="card"
			expand-in-place
			:show-more-aria-label
			:show-less-aria-label
			:empty-label>
			<template #item="{ item }">
				<task-card
					:task="item"
					:position="feedState.items.indexOf( item )"
					@click="() => onTaskCardClick( item.tasktype )"
					@quality-gate-failed="onQualityGateFailed"
				></task-card>
			</template>
			<!-- The design shows empty cards with the large thumbnail while the tasks load. -->
			<template #loading-card>
				<feed-card
					:thumbnail="null"
					thumbnail-size="large"
					force-thumbnail></feed-card>
			</template>
		</feed-panel>
	</div>

	<!-- The menu button belongs in the module header, which the server renders.
		The header slot is the client's mount point there. This teleport nests
		inside the island's own teleport, and follows the body into the dialog or
		the in-page frame, since either stands in for the whole card. -->
	<teleport v-if="headerTarget" :to="headerTarget">
		<module-header-menu
			ref="headerMenu"
			v-slot="{ anchor }"
			:menu-items="menuItems"
			:footer-item="menuFooterItem"
			:button-label="menuButtonLabel"
			@select="onThreeDotMenuItemSelect"
			@menu-open="onThreeDotMenuOpen"
		>
			<cdx-popover
				v-model:open="shouldPopoverBeOpen"
				class="personal-dashboard-select-interests__popover"
				:anchor="anchor"
				:render-in-place="true"
				placement="left"
				:primary-action="defaultPopoverAction"
				@primary="popoverGotItAction">
				{{ popoverContent }}
			</cdx-popover>
			<about-panel
				:open="openPanel === 'about'"
				:anchor="anchor"
				:is-narrow="isNarrow"
				@update:open="closePanel"></about-panel>
			<task-types-dialog
				:open="openPanel === 'taskTypes'"
				:get-saved-count="getSavedCount"
				@update:open="closePanel"
				@saved="onTaskTypesSaved"></task-types-dialog>
			<interest-selector-dialog
				:stacked-actions="true"
				:open="openPanel === 'interests'"
				:get-saved-count="getSavedCount"
				@update:open="closeInterestSelectorPanel"
				@saved="onInterestsSaved"></interest-selector-dialog>
		</module-header-menu>
	</teleport>
</template>

<script>
const { computed, defineComponent, nextTick, ref, inject, watch } = require( 'vue' );
const { CdxPopover } = require( './codex.js' );
const {
	FeedCard,
	FeedPanel,
	FULL_LIMIT,
	ModuleHeaderMenu,
} = require( 'ext.personalDashboard.common' );
const AboutPanel = require( './AboutPanel.vue' );
const QualityGateDialog = require( './QualityGateDialog.vue' );
const TaskCard = require( './TaskCard.vue' );
const TaskTypesDialog = require( './TaskTypesDialog.vue' );
const InterestSelectorDialog = require( './InterestSelectorDialog.vue' );
const InterestsNotice = require( './InterestsNotice.vue' );
const NoMatchNotice = require( './NoMatchNotice.vue' );
const WelcomeBackDialog = require( './WelcomeBackDialog.vue' );
const { CONSTANTS, newcomerTasks } = require( 'ext.growthExperiments.DataStore' );
const { useSuggestedEditsFeed } = require( './useSuggestedEditsFeed.js' );
const {
	cdxIconConfigure,
	cdxIconLevelNone,
	cdxIconLevelOne,
	cdxIconLevelTwo,
	cdxIconLevelThree,
} = require( './icons.json' );

/** Keep in sync with HomeOnboardingActionLookup::INTERESTS_NOTICE_INTERACTED_PREF. */
const INTERESTS_NOTICE_INTERACTED_PREF = 'growthexperiments-home-interests-notice-interacted';
/** Keep in sync with HomeOnboardingActionLookup::ACTION_WELCOME_BACK. */
const WELCOME_BACK_ACTION = 'show-welcome-back';
/** The below constant should be equal to HomeOnboardingActionLookup::INTERESTS_POPOVER_DISMISSED_PREF. */
const INTERESTS_POPOVER_DISMISSED_PREF = 'growthexperiments-home-interests-popover-dismissed';
/**
 * The HomeOnboardingActionLookup::ACTION_* values that show the notice. The
 * welcome back dialog shows on top of it, and the notice stays when the user
 * closes the dialog.
 */
const INTERESTS_NOTICE_ACTIONS = [ 'show-no-interests-notice', 'show-welcome-back' ];
/**
 * The session storage key for the dismissal of the no match notice. The notice
 * shows again in a new session.
 */
const NO_MATCH_NOTICE_DISMISSED_KEY = 'ge-home-suggestededits-nomatch-notice-dismissed';

/**
 * Pick the Types of edits menu icon from the selected task types.
 * Use the hardest selected difficulty: hard beats medium, medium beats easy.
 * If nothing is selected, use the empty level icon.
 *
 * @param {string[]} selectedIds Selected task type IDs
 * @return {string|Object} Codex icon
 */
function getTaskTypesMenuIcon( selectedIds ) {
	const iconByDifficulty = {
		easy: cdxIconLevelOne,
		medium: cdxIconLevelTwo,
		hard: cdxIconLevelThree,
	};
	const rankByDifficulty = {
		easy: 1,
		medium: 2,
		hard: 3,
	};

	let hardestDifficulty = null;
	for ( const id of selectedIds ) {
		const difficulty = CONSTANTS.ALL_TASK_TYPES[ id ] &&
			CONSTANTS.ALL_TASK_TYPES[ id ].difficulty;
		if ( !rankByDifficulty[ difficulty ] ) {
			continue;
		}
		if (
			hardestDifficulty === null ||
			rankByDifficulty[ difficulty ] > rankByDifficulty[ hardestDifficulty ]
		) {
			hardestDifficulty = difficulty;
		}
	}

	return hardestDifficulty ?
		iconByDifficulty[ hardestDifficulty ] :
		cdxIconLevelNone;
}

/**
 * @param {Object[]} items Feed items
 * @return {boolean} Whether no suggested edits match the selection. Then the items are
 *   fallback items, or there are no items.
 */
function isNoMatch( items ) {
	return items.length === 0 || items[ 0 ].isFallback;
}

/**
 * @param {Object[]} items Feed items
 * @param {Error|null} error The error of the last load
 * @return {string} What filled the feed when no suggested edits matched the selection:
 *   - not_used: suggested edits matched the selection, or the load failed
 *   - selected_task_types: fallback items of the selected task types, for all interests
 *   - all_task_types: fallback items of all the task types that the user can get
 *   - none_found: no fallback items
 */
function getFallbackTier( items, error ) {
	if ( error ) {
		return 'not_used';
	}
	if ( !items.length ) {
		return 'none_found';
	}
	return items[ 0 ].fallbackTier || 'not_used';
}

module.exports = exports = defineComponent( {
	components: {
		AboutPanel,
		CdxPopover,
		FeedCard,
		FeedPanel,
		InterestSelectorDialog,
		InterestsNotice,
		ModuleHeaderMenu,
		NoMatchNotice,
		QualityGateDialog,
		TaskCard,
		TaskTypesDialog,
		WelcomeBackDialog,
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
		const { feedState, load, getSavedCount } = useSuggestedEditsFeed();
		const instrumentation = inject( 'HOME_MODULE_INSTRUMENTATION' );

		const defaultPopoverAction = { label: mw.msg( 'growthexperiments-home-suggestededits-popover-button' ),
			actionType: 'progressive' };
		const shouldPopoverBeOpen = ref( false );
		const popoverDismissed = ref( mw.user.options.get( INTERESTS_POPOVER_DISMISSED_PREF ) === '1' );
		let popoverPending = false;
		// Null when the frame gives no header slot, so no menu shows.
		const headerMenu = ref( null );
		// Which panel the menu has opened, or null for none. One value rather
		// than a flag each: the menu opens one panel at a time.
		const openPanel = ref( null );

		const selectedTaskTypes = newcomerTasks.filters.getSelectedTaskTypes();
		const selectedTaskTypeCount = ref( selectedTaskTypes.length );
		const taskTypesIcon = ref( getTaskTypesMenuIcon( selectedTaskTypes ) );

		const selectedInterestCount = ref( newcomerTasks.filters.getSelectedInterests().length );

		// Only Special:Home sets the action, so other dashboards never show the notice.
		const showInterestsNotice = ref(
			INTERESTS_NOTICE_ACTIONS.includes( mw.config.get( 'wgGEHomeOnboardingAction' ) ),
		);

		// The dialog saves that the user saw it.
		const showWelcomeBack = ref( mw.config.get( 'wgGEHomeOnboardingAction' ) === WELCOME_BACK_ACTION );

		// The task type stays set when the dialog closes, so the text does not
		// disappear while the dialog fades out.
		const showQualityGate = ref( false );
		const qualityGateTaskType = ref( null );

		// True when no suggested edits matched the selection in the last load, which
		// ended with no error. Then the feed shows fallback items or none. A load
		// does not change it, so that the notice stays while the feed loads again
		// after the user changes the filters. The watcher that sets it starts after
		// the first load starts.
		const hasNoMatch = ref( false );
		const noMatchNoticeDismissed = ref(
			mw.storage.session.get( NO_MATCH_NOTICE_DISMISSED_KEY ) === '1',
		);
		const showNoMatchNotice = computed( () => hasNoMatch.value && !noMatchNoticeDismissed.value );
		const noMatchNotice = ref( null );

		// When the notice goes, it can hold the focus: the user dismissed it, or a
		// dialog opened from it gave the focus back to it. Without the notice the
		// focus goes to <body>. Move the focus to the module menu, where the user
		// changes the filters. The watcher runs before the notice leaves the DOM.
		watch( showNoMatchNotice, ( shown ) => {
			const element = noMatchNotice.value && noMatchNotice.value.$el;
			if ( !shown && element && element.contains( document.activeElement ) ) {
				nextTick( () => {
					if ( headerMenu.value ) {
						headerMenu.value.focus();
					}
				} );
			}
		} );

		const onNoMatchNoticeDismissed = () => {
			noMatchNoticeDismissed.value = true;
			mw.storage.session.set( NO_MATCH_NOTICE_DISMISSED_KEY, '1' );
		};

		function onThreeDotMenuOpen() {
			shouldPopoverBeOpen.value = false;
			instrumentation.sendClick( 'module_suggested_edits_options' );
		}

		const possiblyShowPopover = () => {
			if ( !popoverDismissed.value && popoverPending ) {
				shouldPopoverBeOpen.value = true;
				popoverPending = false;
			}
		};

		// The notice holds the focus when it goes, or it gave the focus to the
		// interest selector, which gives the focus back to it on close. Without
		// the notice the focus goes to <body>. Move the focus to the module menu,
		// because the user selects interests there from now on.
		const hideInterestsNotice = () => {
			showInterestsNotice.value = false;
			nextTick( () => {
				if ( headerMenu.value ) {
					headerMenu.value.focus();
				}
			} );
		};

		const onInterestsNoticeDismissed = () => {
			popoverPending = true;
			showInterestsNotice.value = false;
			hideInterestsNotice();
			possiblyShowPopover();
			new mw.Api().saveOption( INTERESTS_NOTICE_INTERACTED_PREF, 1 ).catch( ( error ) => {
				mw.errorLogger.logError(
					new Error( 'Unable to save the interests notice dismissal: ' + error ),
					'error.growthexperiments',
				);
			} );
		};

		const closePanel = () => {
			const interestsClosed = openPanel.value === 'interests';
			openPanel.value = null;
			// When the user leaves the interest selector with no interests, the
			// notice is dismissed. It does not matter if the user opened the
			// selector from the notice, from the menu or from the welcome back
			// dialog. Every exit of the
			// dialog counts: "Done", "Cancel", the close button and Esc. The
			// dialog updates the store before it closes, so the store holds the
			// result of "Done" here. Cancelled selections are discarded, so they
			// also leave the store empty. The user did not see the notice when the
			// no match notice replaces it, so do not dismiss it then.
			if (
				interestsClosed &&
					showInterestsNotice.value &&
					!showNoMatchNotice.value &&
					!newcomerTasks.filters.getSelectedInterests().length
			) {
				onInterestsNoticeDismissed();
			}
		};

		const closeInterestSelectorPanel = () => {
			closePanel();
			if ( popoverPending ) {
				possiblyShowPopover();
			}
		};

		const popoverGotItAction = () => {
			shouldPopoverBeOpen.value = false;
			popoverDismissed.value = true;
			new mw.Api().saveOption( INTERESTS_POPOVER_DISMISSED_PREF, 1 ).catch( ( error ) => {
				mw.errorLogger.logError(
					new Error( 'Unable to save the interests popover dismissal: ' + error ),
					'error.growthexperiments',
				);
			} );
		};

		function sendModuleShownOnceWhenVisible() {
			instrumentation.recordModuleShownAfterVisibleOnce(
				{
					// Only the suggested edits that match the selection. The fallback items
					// do not count, so the meaning stays the same as before the fallback.
					// eslint-disable-next-line camelcase
					has_suggestions: !isNoMatch( feedState.items ),
					fallback: getFallbackTier( feedState.items, feedState.error ),
					interests: selectedInterestCount.value,
					// eslint-disable-next-line camelcase
					task_types: selectedTaskTypeCount.value,
					// eslint-disable-next-line camelcase
					has_error: feedState.error !== null,
				},
			);
		}

		// Here rather than in mounted(): load() raises the loading flag before it
		// awaits anything, and mounted() runs after the first render, so deferring
		// it paints the empty-state line for a frame on every mount.
		load( FULL_LIMIT ).then( () => {
			instrumentation.sendModuleReady();
			sendModuleShownOnceWhenVisible();
		} );

		// After load(), because load() sets the loading flag. Before the first load,
		// the feed has no items, and the notice must not show for that state.
		watch(
			() => [ feedState.isLoading, feedState.error, feedState.items ],
			() => {
				if ( !feedState.isLoading ) {
					hasNoMatch.value = !feedState.error && isNoMatch( feedState.items );
				}
			},
			{ immediate: true },
		);

		/**
		 * @param {string} menuValue
		 */
		function onThreeDotMenuItemSelect( menuValue ) {
			if ( menuValue === 'interests' ) {
				instrumentation.sendClick( 'module_suggested_edits_option_interests' );
			} else if ( menuValue === 'taskTypes' ) {
				instrumentation.sendClick( 'module_suggested_edits_option_types' );
			}
			openPanel.value = menuValue;
		}

		/**
		 * @param {string} cardTaskType
		 */
		function onTaskCardClick( cardTaskType ) {
			// eslint-disable-next-line camelcase
			instrumentation.sendClick( 'module_suggested_edits_suggestion', { suggestion_type: cardTaskType } );
		}

		/**
		 * @param {string} cardTaskType The type of the task that failed a quality gate
		 */
		function onQualityGateFailed( cardTaskType ) {
			qualityGateTaskType.value = cardTaskType;
			showQualityGate.value = true;
		}

		return {
			defaultPopoverAction,
			onThreeDotMenuOpen,
			onThreeDotMenuItemSelect,
			onTaskCardClick,
			onQualityGateFailed,
			qualityGateTaskType,
			showQualityGate,
			feedState,
			headerMenu,
			getSavedCount,
			openPanel,
			// A panel only ever reports itself closed: it is opened from the menu
			// above or from the interests notice, never by its own model.
			closePanel,
			popoverGotItAction,
			shouldPopoverBeOpen,
			showInterestsNotice,
			showNoMatchNotice,
			noMatchNotice,
			onNoMatchNoticeDismissed,
			showWelcomeBack,
			closeInterestSelectorPanel,
			openInterestsFromWelcomeBack: () => {
				showWelcomeBack.value = false;
				openPanel.value = 'interests';
			},
			onTaskTypesSaved: () => {
				const selected = newcomerTasks.filters.getSelectedTaskTypes();
				selectedTaskTypeCount.value = selected.length;
				taskTypesIcon.value = getTaskTypesMenuIcon( selected );
				load( FULL_LIMIT ).then( () => {
					sendModuleShownOnceWhenVisible();
				} );
				instrumentation.sendClick( 'module_suggested_edits_option_types_done' );
			},
			// The dialog saves the interests, and the API reads them for itself, so the
			// feed only has to ask again. Start the load now, so that the feed shows the
			// loading cards immediately. The feed waits for the save before it asks.
			onInterestsSaved: ( saving ) => {
				// Only display the interests popover if saving interests removes the interests notice.
				selectedInterestCount.value = newcomerTasks.filters.getSelectedInterests().length;
				popoverPending = showInterestsNotice.value;
				// No preference to save: with interests stored, the server does not
				// ask for the notice again.
				if ( selectedInterestCount.value > 0 && showInterestsNotice.value ) {
					hideInterestsNotice();
					possiblyShowPopover();
				}
				load( FULL_LIMIT, saving ).then( () => {
					sendModuleShownOnceWhenVisible();
				} );
				instrumentation.sendClick( 'module_suggested_edits_option_interests_done' );
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
					icon: taskTypesIcon.value,
				},
			] ),
			// The footer, not a third ordinary item: design shows a divider above
			// it, and that is what Codex gives a menu footer.
			menuFooterItem: {
				value: 'about',
				label: mw.msg( 'growthexperiments-home-suggestededits-menu-about' ),
			},
			popoverContent: mw.msg( 'growthexperiments-home-suggestededits-popover-content' ),
			showMoreAriaLabel: mw.msg( 'growthexperiments-home-suggestededits-show-more-aria-label' ),
			showLessAriaLabel: mw.msg( 'growthexperiments-home-suggestededits-show-less-aria-label' ),
			// The no match notice replaces the empty state. After the user dismisses
			// the notice, the empty state shows, so that the module is not blank.
			emptyLabel: computed( () => ( showNoMatchNotice.value ?
				'' :
				mw.msg( 'growthexperiments-home-suggestededits-empty' ) ) ),
			menuButtonLabel: mw.msg( 'growthexperiments-home-suggestededits-menu-button-label' ),
		};
	},
} );
</script>
