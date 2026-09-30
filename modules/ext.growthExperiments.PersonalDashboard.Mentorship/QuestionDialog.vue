<template>
	<cdx-dialog
		v-model:open="openInternal"
		class="personal-dashboard-mentorship__question-dialog"
		:title="dialogTitle"
		:use-close-button="true"
		:primary-action="primaryAction"
		:stacked-actions="true"
		@primary="onPrimary"
	>
		<template v-if="status === 'done'">
			<p>{{ confirmation }}</p>
			<p>
				<a :href="viewUrl">{{ viewText }}</a>
			</p>
		</template>
		<template v-else>
			<!-- eslint-disable vue/no-v-html -->
			<div
				ref="noticeRef"
				class="personal-dashboard-mentorship__notice"
				tabindex="-1"
				v-html="notice"
			></div>
			<!-- eslint-enable vue/no-v-html -->
			<cdx-field :status="fieldStatus" :messages="fieldMessages">
				<template #label>
					{{ fieldLabel }}
				</template>
				<cdx-text-area
					v-model="question"
					:placeholder="placeholder"
					:disabled="status === 'sending'"
				></cdx-text-area>
			</cdx-field>
		</template>
		<template v-if="copyrightWarning && status !== 'done'" #footer-text>
			<!-- Parsed server-side from an interface message. Design wants it
			here, though the slot is a <p> and the warning is in a <div>. -->
			<!-- eslint-disable-next-line vue/no-v-html -->
			<span v-html="copyrightWarning"></span>
		</template>
	</cdx-dialog>
</template>

<script>
const { defineComponent, computed, ref, watch } = require( 'vue' );
const { CdxDialog, CdxField, CdxTextArea } = require( './codex.js' );
const apiErrorText = require( './apiErrorText.js' );

module.exports = exports = defineComponent( {
	name: 'MentorshipQuestionDialog',
	components: { CdxDialog, CdxField, CdxTextArea },
	props: {
		open: { type: Boolean, default: false },
		dialogTitle: { type: String, required: true },
		notice: { type: String, required: true },
		// Parsed HTML, or '' when a hook removed the warning.
		copyrightWarning: { type: String, default: '' },
		confirmation: { type: String, required: true },
		viewText: { type: String, required: true },
		storage: { type: String, required: true },
	},
	emits: [ 'update:open' ],
	setup( props, { emit } ) {
		const question = ref( '' );
		// editing -> sending -> done, or -> error (retryable from editing).
		// invalid: publish was clicked with an empty question.
		const status = ref( 'editing' );
		// The API's own error text when a post fails, so a blocked or rate-limited
		// user sees why rather than a generic "try again" they'll retry forever.
		const apiError = ref( null );
		// The posted question's URL, handed back by the poster for the "view your
		// question" link.
		const viewUrl = ref( null );
		const noticeRef = ref( null );
		const api = new mw.Api();

		const openInternal = computed( {
			get: () => props.open,
			set: ( value ) => emit( 'update:open', value ),
		} );

		// A reopen starts fresh: never show a stale confirmation, error, or a
		// previous draft after the dialog has been closed.
		watch( () => props.open, ( isOpen ) => {
			if ( isOpen ) {
				status.value = 'editing';
				question.value = '';
				apiError.value = null;
				viewUrl.value = null;
				// CdxDialog focuses the notice's talk page link. Design wants
				// nothing focused, so move focus to the notice after Codex.
				setTimeout( () => {
					if ( noticeRef.value ) {
						noticeRef.value.focus( { preventScroll: true } );
					}
				} );
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
				disabled: status.value === 'sending',
			},
		);
		const fieldStatus = computed( () => [ 'error', 'invalid' ].includes( status.value ) ?
			'error' : 'default' );
		const fieldMessages = computed( () => {
			if ( status.value === 'invalid' ) {
				return { error: mw.msg( 'growthexperiments-homepage-mentorship-question-empty' ) };
			}
			if ( status.value === 'error' ) {
				return { error: apiError.value ||
					mw.msg( 'growthexperiments-home-mentorship-question-error' ) };
			}
			return {};
		} );

		// Codex guidelines prefer a validation message over a disabled button.
		watch( question, () => {
			if ( status.value === 'invalid' ) {
				status.value = 'editing';
			}
		} );

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
				// The generic retry message shows when the API gives no reason.
				apiError.value = apiErrorText( result );
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
				openInternal.value = false;
			} else if ( question.value.trim() === '' ) {
				status.value = 'invalid';
			} else {
				submit();
			}
		}

		return {
			openInternal,
			question,
			status,
			viewUrl,
			noticeRef,
			fieldLabel: mw.msg( 'growthexperiments-help-panel-questionreview-label' ),
			placeholder: mw.msg( 'growthexperiments-help-panel-question-placeholder' ),
			primaryAction,
			fieldStatus,
			fieldMessages,
			onPrimary,
		};
	},
} );
</script>
