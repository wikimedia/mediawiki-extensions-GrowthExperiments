<template>
	<cdx-dialog
		v-model:open="state.confirm"
		:title="title"
		:use-close-button="!busy"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onConfirm"
		@default="state.confirm = false"
	>
		<p>{{ text }}</p>
		<p v-if="error" class="personal-dashboard-mentorship__status-error">
			{{ error }}
		</p>
	</cdx-dialog>
</template>

<script>
const { defineComponent, computed, ref, watch } = require( 'vue' );
const { CdxDialog } = require( './codex.js' );
const state = require( './state.js' );

// Opt out and opt in are the same confirm -> set state -> reload flow; only the
// target mentorship state and the copy differ, so one dialog covers both. The
// copy is resolved client-side with mw.msg; opt-out-text takes the mentor's
// gender, exposed server-side (resolving GENDER for a username is not reliable
// client-side), matching the Homepage opt-out dialog. The gender config var is
// unset for opt-in, where the copy has no mentor-gender form.
const ACTIONS = {
	optout: {
		targetState: 'optout',
		header: 'growthexperiments-homepage-mentorship-optout-header',
		text: 'growthexperiments-homepage-mentorship-optout-text',
		confirm: 'growthexperiments-homepage-mentorship-optout-optout',
		cancel: 'growthexperiments-homepage-mentorship-optout-cancel',
	},
	optin: {
		targetState: 'enabled',
		header: 'growthexperiments-homepage-mentorship-confirm-dialog-header',
		text: 'growthexperiments-homepage-mentorship-confirm-dialog-text',
		confirm: 'growthexperiments-homepage-mentorship-confirm-dialog-continue',
		cancel: 'growthexperiments-homepage-mentorship-confirm-dialog-cancel',
	},
};

module.exports = defineComponent( {
	name: 'MentorshipStatusDialog',
	components: { CdxDialog },
	props: {
		action: { type: String, required: true },
	},
	setup( props ) {
		const config = ACTIONS[ props.action ];
		const busy = ref( false );
		// The API's own reason when the state change fails (already opted out,
		// blocked, read-only), so a click that appears to do nothing says why.
		const error = ref( null );
		const api = new mw.Api();

		// Clear a stale error when the dialog is reopened.
		watch( () => state.confirm, ( isOpen ) => {
			if ( isOpen ) {
				error.value = null;
			}
		} );

		const primaryAction = computed( () => ( {
			label: mw.msg( config.confirm ),
			actionType: props.action === 'optout' ? 'destructive' : 'progressive',
			disabled: busy.value,
		} ) );
		// Cancel is disabled while the request is in flight: by the time it
		// resolves the state change is already committed server-side, so a
		// mid-request cancel followed by the reload would be a lie.
		const defaultAction = computed( () => ( {
			label: mw.msg( config.cancel ),
			disabled: busy.value,
		} ) );

		function onConfirm() {
			busy.value = true;
			error.value = null;
			// growthsetmenteestatus flips the mentee's mentorship state server-side.
			// Reload so the card re-renders in its new state (mentor card <-> opt-in
			// card); on failure, surface the API's reason and leave the dialog to retry.
			api.postWithToken( 'csrf', {
				action: 'growthsetmenteestatus',
				state: config.targetState,
				errorformat: 'plaintext',
				formatversion: 2,
			} ).then( () => {
				window.location.reload();
			} ).catch( ( code, result ) => {
				busy.value = false;
				const errors = ( result && result.errors ) || [];
				error.value = errors.map( ( e ) => e.text || e[ '*' ] || e.code ).join( '\n' ) || null;
			} );
		}

		return {
			state,
			title: mw.msg( config.header ),
			text: mw.msg( config.text, mw.config.get( 'wgGrowthExperimentsMentorshipMentorGender' ) ),
			error,
			busy,
			primaryAction,
			defaultAction,
			onConfirm,
		};
	},
} );
</script>
