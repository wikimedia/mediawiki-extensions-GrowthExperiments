<template>
	<cdx-dialog
		v-model:open="wrappedOpen"
		:use-close-button="true"
		:title="$i18n( 'growthexperiments-homepage-suggestededits-interest-filters-title' ).text()"
	>
		<div class="ext-growthExperiments-interest-selector-dialog">
			<p
				v-if="taskCount !== null"
				v-i18n-html:growthexperiments-homepage-suggestededits-difficulty-filters-article-count="[ taskCount ]"
				aria-live="polite"
				class="ext-growthExperiments-interest-selector-dialog__task-count"
			></p>
			<interest-selector
				v-model:chips="chips"
			></interest-selector>
			<div
				class="ext-growthExperiments-interest-selector-dialog__buttons"
			>
				<cdx-button
					class="ext-growthExperiments-interest-selector-dialog__save"
					action="progressive"
					weight="primary"
					@click="saveSelection"
				>
					{{ $i18n( 'growthexperiments-homepage-suggestededits-interest-filters-close' ).text() }}
				</cdx-button>
				<cdx-button
					class="ext-growthExperiments-interest-selector-dialog__cancel"
					@click="cancelSelection"
				>
					{{ $i18n( 'growthexperiments-homepage-suggestededits-interest-filters-cancel' ).text() }}
				</cdx-button>
			</div>
		</div>
	</cdx-dialog>
</template>

<script>
const { defineComponent, toRef, ref, watch } = require( 'vue' );
const { CdxButton, CdxDialog, useModelWrapper } = require( '@wikimedia/codex' );
const InterestSelector = require( '../vue-components/InterestSelector.vue' );
const InterestFilters = require( '../ext.growthExperiments.DataStore/InterestFilters.js' );
const rootStore = require( 'ext.growthExperiments.DataStore' );

const COUNT_DEBOUNCE_MS = 300;
/** Keep in sync with AccountSetupHooks::INTEREST_ARTICLES_PROP. */
const INTERESTS_PREF = 'growthexperiments-interest-articles-editing';

// @vue/component
module.exports = exports = defineComponent( {
	name: 'InterestSelectorDialog',
	components: {
		CdxButton,
		CdxDialog,
		InterestSelector,
	},
	props: {
		// eslint-disable-next-line vue/no-unused-properties
		open: {
			type: Boolean,
			default: false,
		},
	},
	emits: [ 'update:open' ],
	setup( props, { emit } ) {
		const tasksStore = rootStore.newcomerTasks;
		const filtersStore = tasksStore.filters;
		const chips = ref( filtersStore.getSelectedInterests()
			.map( ( title ) => ( { label: title, value: title } ) ) );
		// The number of suggestions available for the current selection, already formatted
		// for the user's language. Null while it is unknown, which hides the message.
		const taskCount = ref( null );
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );

		/** @type {jQuery.Promise|null} */
		let countRequest = null;

		/**
		 * Look up how many suggestions the given selection has.
		 *
		 * The selection has not been saved, so it is passed to the API explicitly. That is what
		 * makes the API search for this selection rather than the interests stored for the user,
		 * at the cost of bypassing its cache.
		 *
		 * @param {{label: string, value: string}[]} selectedChips
		 */
		function updateTaskCount( selectedChips ) {
			if ( countRequest ) {
				countRequest.abort();
			}
			countRequest = tasksStore.api.fetchTasks(
				tasksStore.filters.getTaskTypesQuery(),
				new InterestFilters( {
					interests: selectedChips.map( ( chip ) => chip.value ),
				} ),
				// Only the count is needed, but the size still has to match the one the task
				// queue is fetched with. For interests the API reports how many suggestions
				// the request collected rather than how many exist, so asking for fewer tasks
				// here would report a smaller number than the pager goes on to show.
				{ context: 'interestSelectorDialog.updateTaskCount' },
			);
			countRequest.then( ( data ) => {
				taskCount.value = mw.language.convertNumber( data.count );
			} ).catch( ( error ) => {
				// A superseded request is not a failure; the one replacing it sets the count.
				if ( error !== 'abort' ) {
					taskCount.value = null;
				}
			} );
		}

		const updateTaskCountDebounced = mw.util.debounce(
			updateTaskCount, COUNT_DEBOUNCE_MS,
		);

		// The dialog is mounted with the page, so only look up counts once it is open.
		watch( [ chips, wrappedOpen ], ( [ selectedChips, isOpen ] ) => {
			if ( !isOpen ) {
				return;
			}
			updateTaskCountDebounced( selectedChips );
		}, { immediate: true } );

		/**
		 * Store the selection, rebuild the task queue from it and close the dialog.
		 *
		 * The queue is fetched without passing any interests, so that the API reads the ones
		 * just stored and can cache the result. That only works once the preference has been
		 * written, hence the refetch waiting on the save. The store is updated before the
		 * dialog closes, so that dismissing it afterwards has nothing to discard.
		 */
		function saveSelection() {
			const interests = chips.value.map( ( chip ) => chip.value );
			if ( interests.join( '|' ) !== filtersStore.getSelectedInterests().join( '|' ) ) {
				const prefValue = JSON.stringify( interests );
				// Update the local copies too, so that the filter button and anything else
				// reading the interests sees the new selection without reloading the page.
				filtersStore.setSelectedInterests( interests );
				mw.user.options.set( INTERESTS_PREF, prefValue );
				new mw.Api().saveOption( INTERESTS_PREF, prefValue ).then(
					() => tasksStore.fetchTasks( 'interestSelectorDialog.saveSelection' ),
				).catch( ( error ) => {
					mw.errorLogger.logError(
						new Error( 'Unable to save selected interests: ' + error ),
						'error.growthexperiments',
					);
				} );
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
		} );

		return {
			chips,
			taskCount,
			wrappedOpen,
			saveSelection,
			cancelSelection,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-interest-selector-dialog {
  &__buttons {
    margin-top: @spacing-100;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

	&__save,
	&__cancel {
		width: 100%;
		justify-content: center;
	}

	&__cancel {
		margin-top: @spacing-50;
	}
}
</style>
