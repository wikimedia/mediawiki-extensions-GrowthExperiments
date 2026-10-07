<template>
	<cdx-dialog
		v-model:open="openInternal"
		class="growthexperiments-task-types-dialog"
		:title="title"
		:subtitle="subtitle"
		:use-close-button="true"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		:stacked-actions="true"
		@primary="save"
		@default="openInternal = false">
		<cdx-message
			v-if="!selected.length"
			type="error"
			:inline="true">
			{{ emptySelectionLabel }}
		</cdx-message>
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
	</cdx-dialog>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' );
const { CdxCheckbox, CdxDialog, CdxField, CdxMessage } = require( './codex.js' );
const { CONSTANTS, newcomerTasks } = require( 'ext.growthExperiments.DataStore' );

const DIFFICULTIES = [ 'easy', 'medium', 'hard' ];

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

module.exports = exports = defineComponent( {
	name: 'TaskTypesDialog',
	components: { CdxCheckbox, CdxDialog, CdxField, CdxMessage },
	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},
	emits: [ 'update:open', 'saved' ],
	setup( props, { emit } ) {
		const { api, filters } = newcomerTasks;
		const allTaskTypes = CONSTANTS.ALL_TASK_TYPES;

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

		// A local copy, so that Cancel discards the changes without a store rollback.
		const selected = ref( [] );
		// null while the count is not known.
		const count = ref( null );
		const isCounting = ref( false );
		let countRequest = null;

		const fetchCount = mw.util.debounce( () => {
			const request = api.fetchTasks( selected.value, filters.getFiltersQuery(), {
				context: 'personaldashboard_suggestededits_filters',
			} );
			countRequest = request;
			request.then( ( result ) => {
				if ( countRequest === request ) {
					count.value = result.count;
					isCounting.value = false;
				}
			}, () => {
				// GrowthTasksApi logs real errors. An abort means a newer request runs.
				if ( countRequest === request ) {
					count.value = null;
					isCounting.value = false;
				}
			} );
		}, 300 );

		watch( selected, () => {
			if ( countRequest ) {
				countRequest.abort();
				countRequest = null;
			}
			isCounting.value = true;
			fetchCount();
		}, { deep: true } );

		watch( () => props.open, ( isOpen ) => {
			if ( isOpen ) {
				selected.value = filters.getSelectedTaskTypes().slice();
			} else {
				if ( countRequest ) {
					countRequest.abort();
					countRequest = null;
				}
			}
		}, { immediate: true } );

		const openInternal = computed( {
			get: () => props.open,
			set: ( value ) => emit( 'update:open', value ),
		} );

		// Keep the last count while the next one loads, as InterestSelectorDialog does.
		const subtitle = computed( () => {
			if ( count.value === null ) {
				return '';
			}
			return mw.message(
				'growthexperiments-homepage-suggestededits-difficulty-filters-subtitle-count',
				mw.language.convertNumber( count.value ),
			).text();
		} );

		// The count can be wrong when no task type is selected (T369742), so check both.
		const primaryAction = computed( () => ( {
			label: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-close' ).text(),
			actionType: 'progressive',
			disabled: !selected.value.length || isCounting.value || count.value === 0,
		} ) );

		const save = () => {
			filters.setSelectedTaskTypes( selected.value.slice() );
			// The feed does not wait for the save: fetchTasks sends the task types itself.
			filters.savePreferences();
			emit( 'saved' );
			emit( 'update:open', false );
		};

		return {
			groups,
			selected,
			openInternal,
			subtitle,
			primaryAction,
			save,
			defaultAction: {
				label: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-cancel' ).text(),
			},
			title: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' ).text(),
			emptySelectionLabel: mw.message(
				'growthexperiments-homepage-suggestededits-difficulty-filter-error',
			).text(),
		};
	},
} );
</script>
