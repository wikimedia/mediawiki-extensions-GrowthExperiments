<template>
	<cdx-dialog
		v-model:open="wrappedOpen"
		class="growthexperiments-task-types-dialog"
		:title="title"
		:use-close-button="true"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		:stacked-actions="true"
		@primary="save"
		@default="wrappedOpen = false">
		<cdx-field
			v-for="group in groups"
			:key="group.difficulty"
			:is-fieldset="true">
			<template #label>
				{{ group.label }}
			</template>
			<cdx-checkbox
				v-for="taskType in group.taskTypes"
				:key="taskType.id"
				v-model="selected"
				:input-value="taskType.id"
				:disabled="taskType.disabled">
				{{ taskType.label }}
				<template v-if="taskType.description" #description>
					{{ taskType.description }}
				</template>
			</cdx-checkbox>
		</cdx-field>
		<!-- Always render the region, also while the count is unknown. Screen readers do not
		announce a change to a live region that they did not find before the change. -->
		<template #footer-text>
			<c-live-region
				:class="{ 'growthexperiments-task-types-dialog__live-region--none': countStatus === 'none' }"
				:message="countMessage"
				visible
			></c-live-region>
		</template>
	</cdx-dialog>
</template>

<script>
const { computed, defineComponent, ref, toRef, watch } = require( 'vue' );
const { CdxCheckbox, CdxDialog, CdxField, useModelWrapper } = require( './codex.js' );
const { CONSTANTS, newcomerTasks } = require( 'ext.growthExperiments.DataStore' );
const InterestFilters = require( '../ext.growthExperiments.DataStore/InterestFilters.js' );
const CLiveRegion = require( './CLiveRegion.vue' );
const useSuggestedEditsCount = require( './useSuggestedEditsCount.js' );

const DIFFICULTIES = [ 'easy', 'medium', 'hard' ];
const allTaskTypes = CONSTANTS.ALL_TASK_TYPES;

/**
 * @param {Object} taskType Task type data from TaskTypes.json
 * @return {string|null}
 */
function getDescription( taskType ) {
	if ( taskType.unavailable ) {
		return taskType.extraMessages ? taskType.extraMessages.unavailable : null;
	}
	if ( 'filterIcon' in taskType.iconData ) {
		// Messages that can be used here:
		// * growthexperiments-homepage-suggestededits-tasktype-machine-description
		return mw.message( taskType.iconData.descriptionMessageKey ).text();
	}
	return null;
}

// Task types that configuration disables are not in ALL_TASK_TYPES at all.
const groups = DIFFICULTIES.map( ( difficulty ) => ( {
	difficulty,
	// The following messages are used here:
	// * growthexperiments-homepage-startediting-dialog-difficulty-level-easy-label
	// * growthexperiments-homepage-startediting-dialog-difficulty-level-medium-label
	// * growthexperiments-homepage-startediting-dialog-difficulty-level-hard-label
	label: mw.message(
		'growthexperiments-homepage-startediting-dialog-difficulty-level-' + difficulty + '-label',
	).text(),
	taskTypes: Object.keys( allTaskTypes )
		.filter( ( id ) => allTaskTypes[ id ].difficulty === difficulty )
		.map( ( id ) => ( {
			id,
			label: allTaskTypes[ id ].messages.label,
			disabled: !!allTaskTypes[ id ].disabled,
			description: getDescription( allTaskTypes[ id ] ),
		} ) ),
} ) ).filter( ( group ) => group.taskTypes.length );

const allTaskTypeIds = CONSTANTS.AVAILABLE_TASK_TYPE_IDS;

module.exports = exports = defineComponent( {
	name: 'TaskTypesDialog',
	components: { CdxCheckbox, CdxDialog, CdxField, CLiveRegion },
	props: {
		open: {
			type: Boolean,
			default: false,
		},
		/**
		 * Get the count of the stored selection, if the parent has it. The function returns a
		 * promise that resolves with an object that has a `count` property, or null. The
		 * dialog opens with the stored selection, so it shows this count when it opens, and
		 * it does not ask for it.
		 */
		getSavedCount: {
			type: Function,
			default: null,
		},
	},
	emits: [ 'update:open', 'saved' ],
	setup( props, { emit } ) {
		const { api, filters } = newcomerTasks;
		// A local copy, so that Cancel discards the changes without a store rollback.
		const selected = ref( [] );
		// An empty selection means all task types. Return a new array each time, so that the
		// count updates also when the selection stays empty.
		const effectiveSelection = computed( () => ( selected.value.length ?
			selected.value : allTaskTypeIds ).slice() );
		const {
			status: countStatus,
			message: countMessage,
			update: updateCount,
			show: showCount,
			reset: resetCount,
		} = useSuggestedEditsCount( ( taskTypes ) => api.fetchTasks(
			taskTypes,
			// Send the interests, so that the API does not use its cache. The cache holds one task
			// set per user. A request with other task types replaces it, and the next feed load
			// must build it again.
			new InterestFilters( { interests: filters.getSelectedInterests() } ),
			{ context: 'personaldashboard_suggestededits_filters' },
		) );

		// True from the moment the dialog opens until the first count. That count is for the
		// stored selection.
		let isOpening = false;

		watch( effectiveSelection, ( taskTypes ) => {
			const savedCount = isOpening && props.getSavedCount && props.getSavedCount();
			isOpening = false;
			if ( savedCount ) {
				showCount( savedCount );
				return;
			}
			updateCount( taskTypes.slice() );
		}, { deep: true } );

		watch( () => props.open, ( isOpen ) => {
			if ( isOpen ) {
				isOpening = true;
				selected.value = filters.getSelectedTaskTypes().slice();
			} else {
				isOpening = false;
				// The count is for a selection that is saved or discarded now. Remove it, and
				// stop a count that has not finished, so that the dialog does not show it when
				// it opens again.
				resetCount();
			}
		}, { immediate: true } );

		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );

		// Do not block Done while the count loads, or when it is zero or fails: the user
		// decides. An empty selection means all task types, see effectiveSelection.
		const primaryAction = {
			label: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-close' ).text(),
			actionType: 'progressive',
		};

		const save = () => {
			filters.setSelectedTaskTypes( effectiveSelection.value.slice() );
			// The feed does not wait for the save: fetchTasks sends the task types itself.
			filters.savePreferences();
			emit( 'saved' );
			emit( 'update:open', false );
		};

		return {
			groups,
			selected,
			wrappedOpen,
			countMessage,
			countStatus,
			primaryAction,
			save,
			defaultAction: {
				label: mw.message( 'growthexperiments-home-suggestededits-difficulty-filters-cancel' ).text(),
			},
			title: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' ).text(),
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.growthexperiments-task-types-dialog {
	.skin-vector-2022 & {
		// Fix the height on desktop. Keep it the same as in InterestSelectorDialog, so that the
		// two dialogs are consistent when the user switches between them. There is no Codex token
		// for 48rem. On a short screen, keep the Codex limit, so that the dialog stays in the
		// viewport.
		// Escape the value. Less has its own min() function, which drops the calc().
		height: ~'min( 48rem, calc( @{size-viewport-height-full} - @{size-200} ) )';
	}

	&__live-region--none {
		font-weight: @font-weight-bold;
	}
}
</style>
