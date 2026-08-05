<template>
	<cdx-dialog
		v-model:open="state.open"
		:title="dialogTitle"
		:use-close-button="true"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onPrimary"
		@default="state.open = false"
	>
		<template v-if="status === 'done'">
			<p>{{ confirmation }}</p>
			<p>
				<a :href="viewUrl">{{ viewText }}</a>
			</p>
		</template>
		<template v-else>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<div v-html="notice"></div>
			<cdx-field :status="fieldStatus" :messages="fieldMessages">
				<cdx-text-area
					v-model="question"
					:aria-label="dialogTitle"
					:placeholder="placeholder"
					:disabled="status === 'sending'"
				></cdx-text-area>
			</cdx-field>
		</template>
	</cdx-dialog>
</template>

<script>
const { defineComponent, computed, ref, watch } = require( 'vue' );
const { CdxDialog, CdxField, CdxTextArea } = require( './codex.js' );
const state = require( './state.js' );

// ext.personalDashboard.common carries PersonalDashboard's shared client utils.
// It is only registered when PersonalDashboard is installed, which is exactly
// when this module renders, so treat it as a soft dependency rather than a hard
// ResourceLoader one: load it lazily and fall back to a generic error if absent.
let parseApiStatus = null;
if ( mw.loader.getState( 'ext.personalDashboard.common' ) !== null ) {
	mw.loader.using( 'ext.personalDashboard.common' ).then( ( require ) => {
		parseApiStatus = require( 'ext.personalDashboard.common' ).utils.parseApiStatus;
	} ).catch( () => {
		// Leave parseApiStatus null; error reporting falls back to the generic message.
	} );
}

module.exports = defineComponent( {
	name: 'MentorshipQuestionDialog',
	components: { CdxDialog, CdxField, CdxTextArea },
	props: {
		dialogTitle: { type: String, required: true },
		notice: { type: String, required: true },
		confirmation: { type: String, required: true },
		viewText: { type: String, required: true },
		storage: { type: String, required: true },
	},
	setup( props ) {
		const question = ref( '' );
		// editing -> sending -> done, or -> error (retryable from editing).
		const status = ref( 'editing' );
		// The API's own error text when a post fails, so a blocked or rate-limited
		// user sees why rather than a generic "try again" they'll retry forever.
		const apiError = ref( null );
		// The posted question's URL, handed back by the poster for the "view your
		// question" link.
		const viewUrl = ref( null );
		const api = new mw.Api();

		// A reopen starts fresh: never show a stale confirmation, error, or a
		// previous draft after the dialog has been closed.
		watch( () => state.open, ( isOpen ) => {
			if ( isOpen ) {
				status.value = 'editing';
				question.value = '';
				apiError.value = null;
				viewUrl.value = null;
			}
		} );

		const primaryAction = computed( () => status.value === 'done' ?
			{
				label: mw.msg( 'growthexperiments-homepage-mentorship-optout-confirmation-done' ),
				actionType: 'progressive',
			} :
			{
				label: mw.msg( 'growthexperiments-help-panel-submit-question-button-text' ),
				actionType: 'progressive',
				disabled: status.value === 'sending' || question.value.trim() === '',
			},
		);
		const defaultAction = computed( () => status.value === 'done' ?
			undefined :
			{ label: mw.msg( 'growthexperiments-homepage-mentorship-confirm-dialog-cancel' ) },
		);
		const fieldStatus = computed( () => status.value === 'error' ? 'error' : 'default' );
		const fieldMessages = computed( () => status.value === 'error' ?
			{ error: apiError.value ||
				mw.msg( 'growthexperiments-homepage-mentorship-question-error' ) } :
			{},
		);

		function submit() {
			status.value = 'sending';
			apiError.value = null;
			// GrowthExperiments' question poster owns the write: it resolves the
			// mentor's talk page, signs and sections the post, tags it server-side
			// (which the manual edit API can't do), and returns the URL to view it.
			api.postWithToken( 'csrf', {
				action: 'helppanelquestionposter',
				source: 'mentor-homepage',
				body: question.value.trim(),
				formatversion: 2,
				errorformat: 'plaintext',
			} ).then( ( response ) => {
				viewUrl.value = response.helppanelquestionposter.viewquestionurl;
				status.value = 'done';
				refreshRecentQuestions();
			} ).catch( ( code, result ) => {
				status.value = 'error';
				// Surface the API's reason (blocked, read-only, rate limited) when
				// there is one; fall back to the generic retry message otherwise.
				const messages = parseApiStatus && result && result.errors ?
					parseApiStatus( result.errors ) : [];
				apiError.value = messages.join( '\n' ) || null;
			} );
		}

		// Pull the freshly rendered recent-questions section from the same
		// GrowthExperiments API the homepage uses and swap it in, so the
		// just-asked question appears without a reload. Best-effort: on failure
		// the list still updates on the next page load.
		function refreshRecentQuestions() {
			api.get( {
				action: 'homepagequestionstore',
				storage: props.storage,
				uselang: mw.config.get( 'wgUserLanguage' ),
				formatversion: 2,
			} ).then( ( r ) => {
				const html = r.homepagequestionstore && r.homepagequestionstore.html;
				const current = document.querySelector( '.recent-questions-' + props.storage );
				if ( html && current ) {
					current.outerHTML = html;
				}
			} ).catch( () => {
				// Best-effort: the list still refreshes on the next page load.
			} );
		}

		function onPrimary() {
			if ( status.value === 'done' ) {
				state.open = false;
			} else {
				submit();
			}
		}

		return {
			state,
			question,
			status,
			viewUrl,
			placeholder: mw.msg( 'growthexperiments-help-panel-question-placeholder' ),
			primaryAction,
			defaultAction,
			fieldStatus,
			fieldMessages,
			onPrimary,
		};
	},
} );
</script>
