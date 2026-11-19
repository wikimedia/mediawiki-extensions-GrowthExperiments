<template>
	<cdx-dialog
		v-model:open="wrappedOpen"
		class="growthexperiments-quality-gate-dialog"
		:title="title"
		:hide-title="true"
		:primary-action="primaryAction"
		@primary="wrappedOpen = false">
		<!-- The message comes from mw.message().parse(), which escapes it. -->
		<!-- eslint-disable vue/no-v-html -->
		<p
			v-if="messageHtml"
			class="growthexperiments-quality-gate-dialog__message"
			v-html="messageHtml"></p>
		<!-- eslint-enable vue/no-v-html -->
	</cdx-dialog>
</template>

<script>
const { computed, defineComponent, toRef } = require( 'vue' );
const { CdxDialog, useModelWrapper } = require( './codex.js' );

/**
 * The message prefix for each task type that has a daily limit. The keys are
 * the same as in QualityGate.js, so the text is the same as on Special:Homepage.
 */
const MESSAGE_PREFIXES = {
	'image-recommendation': 'growthexperiments-addimage',
	'section-image-recommendation': 'growthexperiments-addsectionimage',
	'link-recommendation': 'growthexperiments-addlink',
};

/**
 * Tells the user that they cannot do more tasks of this type today. The
 * parent opens the dialog when a task card fails the dailyLimit quality gate.
 */
module.exports = exports = defineComponent( {
	name: 'QualityGateDialog',
	components: { CdxDialog },
	props: {
		// useModelWrapper reads this through toRef(), which the rule does not see.
		// eslint-disable-next-line vue/no-unused-properties
		open: {
			type: Boolean,
			default: false,
		},
		// The type of the task that failed the gate, for example 'link-recommendation'.
		taskType: {
			type: String,
			default: null,
		},
	},
	emits: [ 'update:open' ],
	setup( props, { emit } ) {
		const prefix = computed( () => MESSAGE_PREFIXES[ props.taskType ] || null );
		return {
			wrappedOpen: useModelWrapper( toRef( props, 'open' ), emit, 'update:open' ),
			// The following messages are used here:
			// * growthexperiments-addimage-daily-task-limit-exceeded
			// * growthexperiments-addsectionimage-daily-task-limit-exceeded
			// * growthexperiments-addlink-daily-task-limit-exceeded
			// Parse, not text: the messages use {{GENDER:}}.
			messageHtml: computed( () => prefix.value ?
				mw.message( prefix.value + '-daily-task-limit-exceeded' ).parse() :
				'',
			),
			// The Homepage dialog has no title. The title is hidden here too, but
			// it gives the dialog an accessible name.
			// The following messages are used here:
			// * growthexperiments-homepage-suggestededits-tasktype-name-image-recommendation
			// * growthexperiments-homepage-suggestededits-tasktype-name-section-image-recommendation
			// * growthexperiments-homepage-suggestededits-tasktype-name-link-recommendation
			title: computed( () => props.taskType ?
				mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-name-' + props.taskType ) :
				'',
			),
			// The following messages are used here:
			// * growthexperiments-addimage-daily-task-limit-exceeded-dialog-button
			// * growthexperiments-addsectionimage-daily-task-limit-exceeded-dialog-button
			// * growthexperiments-addlink-daily-task-limit-exceeded-dialog-button
			primaryAction: computed( () => ( {
				label: prefix.value ?
					mw.msg( prefix.value + '-daily-task-limit-exceeded-dialog-button' ) :
					'',
				actionType: 'progressive',
			} ) ),
		};
	},
} );
</script>

<style lang="less">
.growthexperiments-quality-gate-dialog {
	// The messages separate two paragraphs with a blank line. Keep the line breaks.
	&__message {
		white-space: pre-line;
	}
}
</style>
