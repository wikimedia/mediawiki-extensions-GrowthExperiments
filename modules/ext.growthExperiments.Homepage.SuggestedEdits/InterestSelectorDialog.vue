<template>
	<cdx-dialog
		v-model:open="wrappedOpen"
		:use-close-button="true"
		:title="$i18n( 'growthexperiments-homepage-suggestededits-interest-filters-title' ).text()"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		:fixed-height="isMobile"
		class="ext-growthExperiments-interest-selector-dialog"
		@primary="saveSelection"
		@default="cancelSelection"
	>
		<interest-selector
			v-model:chips="chips"
		></interest-selector>
		<!-- Always render the region, also while the count is unknown. Screen readers do not
		announce a change to a live region that they did not find before the change. -->
		<template #footer-text>
			<c-live-region
				:class="{ 'ext-growthExperiments-interest-selector-dialog__live-region--none': taskCountStatus === 'none' }"
				:message="taskCountMessage"
				visible
			></c-live-region>
			<!-- Outside the live region: screen readers announce only the status. The space
			separates the hint from the status. Vue removes a plain space at the start of an
			element, so use an interpolation. -->
			<!-- eslint-disable vue/no-useless-mustaches -->
			<span
				v-if="showSwitchHint"
				class="ext-growthExperiments-interest-selector-dialog__switch-hint"
			>{{ ' ' }}<span
				v-i18n-html:growthexperiments-home-suggestededits-interest-filters-none-hint="[ switchUrl ]"
				@click="onSwitchHintClick"
			></span></span>
			<!-- eslint-enable vue/no-useless-mustaches -->
		</template>
	</cdx-dialog>
</template>

<script>
const { computed, defineComponent, toRef, ref, watch } = require( 'vue' );
const { CdxDialog, useModelWrapper } = require( './codex.js' );
const InterestSelector = require( './InterestSelector.vue' );
const CLiveRegion = require( './CLiveRegion.vue' );
const useSuggestedEditsCount = require( './useSuggestedEditsCount.js' );
const InterestFilters = require( '../ext.growthExperiments.DataStore/InterestFilters.js' );
const rootStore = require( 'ext.growthExperiments.DataStore' );

/** Keep in sync with AccountSetupHooks::INTEREST_ARTICLES_PROP. */
const INTERESTS_PREF = 'growthexperiments-interest-articles-editing';

// @vue/component
module.exports = exports = defineComponent( {
	name: 'InterestSelectorDialog',
	components: {
		CdxDialog,
		CLiveRegion,
		InterestSelector,
	},
	props: {
		// eslint-disable-next-line vue/no-unused-properties
		open: {
			type: Boolean,
			default: false,
		},
		/**
		 * Whether to link to the types of edits dialog when no suggested edits match. Set it
		 * only when the parent handles the "switch" event.
		 */
		switchable: {
			type: Boolean,
			default: false,
		},
	},
	emits: [ 'update:open', 'saved', 'switch' ],
	setup( props, { emit } ) {
		const tasksStore = rootStore.newcomerTasks;
		const filtersStore = tasksStore.filters;
		const chips = ref( filtersStore.getSelectedInterests()
			.map( ( title ) => ( { label: title, value: title } ) ) );
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );
		const primaryAction = {
			label: mw.msg( 'growthexperiments-homepage-suggestededits-interest-filters-close' ),
			actionType: 'progressive',
		};
		const defaultAction = {
			label: mw.msg( 'growthexperiments-homepage-suggestededits-interest-filters-cancel' ),
		};

		/**
		 * Look up how many suggestions the given selection has.
		 *
		 * The selection has not been saved, so it is passed to the API explicitly. That is what
		 * makes the API search for this selection rather than the interests stored for the user,
		 * at the cost of bypassing its cache.
		 */
		const {
			status: taskCountStatus,
			message: taskCountMessage,
			update: updateTaskCount,
			reset: resetTaskCount,
		} = useSuggestedEditsCount( ( selectedChips ) => tasksStore.api.fetchTasks(
			tasksStore.filters.getTaskTypesQuery(),
			new InterestFilters( {
				interests: selectedChips.map( ( chip ) => chip.value ),
			} ),
			// Only the count is needed, but the size still has to match the one the task
			// queue is fetched with. For interests the API reports how many suggestions
			// the request collected rather than how many exist, so asking for fewer tasks
			// here would report a smaller number than the pager goes on to show.
			{ context: 'interestSelectorDialog.updateTaskCount' },
		) );

		// The dialog is mounted with the page, so only look up counts once it is open.
		watch( [ chips, wrappedOpen ], ( [ selectedChips, isOpen ] ) => {
			if ( !isOpen ) {
				return;
			}
			updateTaskCount( selectedChips );
		}, { immediate: true } );

		/**
		 * Store the selection, report it and close the dialog.
		 *
		 * The caller rebuilds its own suggestions, because the dashboard island and the
		 * Homepage module hold different ones. "saved" does not wait for the save, so that
		 * the caller can show that it loads immediately. It gives the caller a promise for
		 * the save. The caller asks the API without any interests, so that the API reads the
		 * ones just stored and can cache the result. Thus, the caller must wait for the
		 * promise before it asks. The promise resolves also when the save fails. This dialog
		 * logs the failure. The store is updated before the dialog closes, so that dismissing
		 * it afterwards has nothing to discard.
		 */
		function saveSelection() {
			const interests = chips.value.map( ( chip ) => chip.value );
			if ( interests.join( '|' ) !== filtersStore.getSelectedInterests().join( '|' ) ) {
				const prefValue = JSON.stringify( interests );
				// Update the local copies too, so that the filter button and anything else
				// reading the interests sees the new selection without reloading the page.
				filtersStore.setSelectedInterests( interests );
				mw.user.options.set( INTERESTS_PREF, prefValue );
				const saving = new mw.Api().saveOption( INTERESTS_PREF, prefValue ).catch( ( error ) => {
					mw.errorLogger.logError(
						new Error( 'Unable to save selected interests: ' + error ),
						'error.growthexperiments',
					);
				} );
				emit( 'saved', saving );
			}
			wrappedOpen.value = false;
		}

		/**
		 * Close the dialog without storing the selection.
		 *
		 * The edits are discarded by the watcher below, which covers every way of dismissing
		 * the dialog rather than just this button.
		 */
		function cancelSelection() {
			wrappedOpen.value = false;
		}

		// Dismissing the dialog, with either close control or with Esc, leaves the stored selection
		// alone. Only the button above saves, so the edits made since it was opened are thrown
		// away; the dialog is mounted for the lifetime of the page and would otherwise reopen
		// showing a selection the user walked away from.
		watch( wrappedOpen, ( isOpen, wasOpen ) => {
			if ( isOpen || !wasOpen ) {
				return;
			}
			chips.value = filtersStore.getSelectedInterests()
				.map( ( title ) => ( { label: title, value: title } ) );
			// The count is for the discarded selection. Remove it, and stop a lookup that has
			// not finished, so that the dialog does not show it when it opens again.
			resetTaskCount();
		} );

		const showSwitchHint = computed(
			() => props.switchable && taskCountStatus.value === 'none',
		);
		// jqueryMsg accepts only a path or a URL as a link target. Link to the current page,
		// and open the other dialog when the user clicks the link.
		const switchUrl = mw.util.getUrl();

		/**
		 * @param {MouseEvent} event
		 */
		function onSwitchHintClick( event ) {
			if ( !event.target.closest( 'a' ) ) {
				return;
			}
			event.preventDefault();
			emit( 'switch' );
		}

		const isMobile = mw.config.get( 'skin' ) === 'minerva';

		return {
			isMobile,
			chips,
			wrappedOpen,
			taskCountMessage,
			taskCountStatus,
			showSwitchHint,
			switchUrl,
			onSwitchHintClick,
			primaryAction,
			defaultAction,
			saveSelection,
			cancelSelection,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-interest-selector-dialog {
	.skin-vector-2022 & {
		// Fix the height on desktop so that the layout does not shift when selecting interests.
		// Keep it the same as in TaskTypesDialog, so that the two dialogs are consistent when
		// the user switches between them. There is no Codex token for 48rem. On a short screen,
		// keep the Codex limit, so that the dialog stays in the viewport.
		// Escape the value. Less has its own min() function, which drops the calc().
		height: ~'min( 48rem, calc( @{size-viewport-height-full} - @{size-200} ) )';
	}

	&__live-region--none {
		font-weight: @font-weight-bold;
	}

	&__switch-hint a {
		font-weight: @font-weight-bold;

		&:visited {
			// The link points at the current page, so the browser always shows it as visited.
			// It acts as a button, so keep the unvisited link color.
			color: @color-progressive;
		}
	}
}
</style>
