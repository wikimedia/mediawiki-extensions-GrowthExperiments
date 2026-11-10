<template>
	<cdx-dialog
		v-model:open="openInternal"
		class="growthexperiments-task-types-dialog"
		:title="title"
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
		<!-- Always render the region, also while the count is unknown. Screen readers do not
		announce a change to a live region that they did not find before the change. -->
		<template #footer-text>
			<c-live-region
				:class="{ 'growthexperiments-task-types-dialog__live-region--none': countStatus === 'none' }"
				:message="countMessage"
				visible
			></c-live-region>
			<!-- Outside the live region: screen readers announce only the status. The space
			separates the hint from the status. Vue removes a plain space at the start of an
			element, so use an interpolation. -->
			<!-- eslint-disable vue/no-useless-mustaches -->
			<span
				v-if="countStatus === 'none'"
				class="growthexperiments-task-types-dialog__switch-hint"
			>{{ ' ' }}<span
				v-i18n-html:growthexperiments-home-suggestededits-difficulty-filters-none-hint="[ switchUrl ]"
				@click="onSwitchHintClick"
			></span></span>
			<!-- eslint-enable vue/no-useless-mustaches -->
		</template>
	</cdx-dialog>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' );
const { CdxCheckbox, CdxDialog, CdxField, CdxMessage } = require( './codex.js' );
const { CONSTANTS, newcomerTasks } = require( 'ext.growthExperiments.DataStore' );
const CLiveRegion = require( './CLiveRegion.vue' );
const useSuggestedEditsCount = require( './useSuggestedEditsCount.js' );

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
	components: { CdxCheckbox, CdxDialog, CdxField, CdxMessage, CLiveRegion },
	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},
	emits: [ 'update:open', 'saved', 'switch' ],
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
		const {
			status: countStatus,
			message: countMessage,
			update: updateCount,
			reset: resetCount,
		} = useSuggestedEditsCount( ( taskTypes ) => api.fetchTasks(
			taskTypes,
			filters.getFiltersQuery(),
			{ context: 'personaldashboard_suggestededits_filters' },
		) );

		watch( selected, () => {
			updateCount( selected.value.slice() );
		}, { deep: true } );

		watch( () => props.open, ( isOpen ) => {
			if ( isOpen ) {
				selected.value = filters.getSelectedTaskTypes().slice();
			} else {
				// The count is for a selection that is saved or discarded now. Remove it, and
				// stop a count that has not finished, so that the dialog does not show it when
				// it opens again.
				resetCount();
			}
		}, { immediate: true } );

		// jqueryMsg accepts only a path or a URL as a link target. Link to the current page,
		// and open the other dialog when the user clicks the link.
		const switchUrl = mw.util.getUrl();

		/**
		 * @param {MouseEvent} event
		 */
		const onSwitchHintClick = ( event ) => {
			if ( !event.target.closest( 'a' ) ) {
				return;
			}
			event.preventDefault();
			emit( 'switch' );
		};

		const openInternal = computed( {
			get: () => props.open,
			set: ( value ) => emit( 'update:open', value ),
		} );

		// Do not block Done while the count loads, or when it is zero or fails: the user
		// decides. An empty selection has no task types to search, and the count for it can
		// be wrong (T369742), so do not let the user save it.
		const primaryAction = computed( () => ( {
			label: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-close' ).text(),
			actionType: 'progressive',
			disabled: !selected.value.length,
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
			countMessage,
			countStatus,
			switchUrl,
			onSwitchHintClick,
			primaryAction,
			save,
			defaultAction: {
				label: mw.message( 'growthexperiments-home-suggestededits-difficulty-filters-cancel' ).text(),
			},
			title: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' ).text(),
			emptySelectionLabel: mw.message(
				'growthexperiments-homepage-suggestededits-difficulty-filter-error',
			).text(),
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
