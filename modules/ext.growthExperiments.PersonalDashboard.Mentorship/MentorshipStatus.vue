<template>
	<p>{{ text }}</p>
	<p v-if="error" class="personal-dashboard-mentorship__status-error">
		{{ error }}
	</p>
	<div class="personal-dashboard-mentorship__status-actions">
		<cdx-button
			:action="action === 'optout' ? 'destructive' : 'progressive'"
			weight="primary"
			:disabled="busy"
			@click="onConfirm"
		>
			{{ confirmLabel }}
		</cdx-button>
		<cdx-button
			:disabled="busy"
			@click="$emit( 'cancel' )"
		>
			{{ cancelLabel }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent, ref, watch } = require( 'vue' );
const { CdxButton } = require( './codex.js' );
const apiErrorText = require( './apiErrorText.js' );

// Opt out and opt in are the same confirm -> set state -> reload flow; only the
// target mentorship state and the copy differ, so one component covers both.
// opt-out-text takes the mentor's gender; opt-in copy has no mentor-gender form.
const ACTIONS = {
	optout: {
		targetState: 'optout',
		text: 'growthexperiments-homepage-mentorship-optout-text',
		confirm: 'growthexperiments-homepage-mentorship-optout-optout',
		cancel: 'growthexperiments-homepage-mentorship-optout-cancel',
	},
	optin: {
		targetState: 'enabled',
		text: 'growthexperiments-homepage-mentorship-confirm-dialog-text',
		confirm: 'growthexperiments-homepage-mentorship-confirm-dialog-continue',
		cancel: 'growthexperiments-homepage-mentorship-confirm-dialog-cancel',
	},
};

module.exports = exports = defineComponent( {
	name: 'MentorshipStatus',
	components: { CdxButton },
	props: {
		action: { type: String, required: true },
		mentorGender: { type: String, default: 'unknown' },
	},
	emits: [ 'cancel', 'busy' ],
	setup( props, { emit } ) {
		const config = ACTIONS[ props.action ];
		const busy = ref( false );
		// Immediate, so a remount (panel swap at the breakpoint) resets the parent.
		watch( busy, ( value ) => emit( 'busy', value ), { immediate: true } );
		// The API's own reason when the state change fails (already opted out,
		// blocked, read-only), so a click that appears to do nothing says why.
		const error = ref( null );
		const api = new mw.Api();

		function onConfirm() {
			busy.value = true;
			error.value = null;
			// Reload so the card re-renders in its new state (mentor card <-> opt-in
			// card); on failure, surface the API's reason and leave the panel to retry.
			api.postWithToken( 'csrf', {
				action: 'growthsetmenteestatus',
				state: config.targetState,
				errorformat: 'plaintext',
				formatversion: 2,
			} ).then( () => {
				window.location.reload();
			} ).catch( ( code, result ) => {
				busy.value = false;
				error.value = apiErrorText( result );
			} );
		}

		return {
			text: mw.msg( config.text, props.mentorGender ),
			confirmLabel: mw.msg( config.confirm ),
			cancelLabel: mw.msg( config.cancel ),
			error,
			busy,
			onConfirm,
		};
	},
} );
</script>
