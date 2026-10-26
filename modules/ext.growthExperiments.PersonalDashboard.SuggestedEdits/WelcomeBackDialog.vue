<template>
	<cdx-dialog
		v-model:open="wrappedOpen"
		class="growthexperiments-welcome-back-dialog"
		:title="title"
		:subtitle="subtitle"
		:use-close-button="true">
		<div class="growthexperiments-welcome-back-dialog__image"></div>
		<!-- The footer slot, not primaryAction: the dialog renders its own action
			buttons at the default size, and design asks for a large button. -->
		<template #footer>
			<cdx-button
				class="growthexperiments-welcome-back-dialog__select-interests"
				action="progressive"
				weight="primary"
				size="large"
				@click="$emit( 'select-interests' )">
				{{ selectInterestsLabel }}
			</cdx-button>
		</template>
	</cdx-dialog>
</template>

<script>
const { defineComponent, toRef } = require( 'vue' );
const { CdxButton, CdxDialog, useModelWrapper } = require( './codex.js' );

/** Keep in sync with HomeOnboardingActionLookup::INTRO_SEEN_PREF. */
const INTRO_SEEN_PREF = 'growthexperiments-home-intro-seen';

/**
 * Welcomes back a user who had Special:Homepage enabled and has no interests
 * (T441152). The dialog saves that the user saw it. The parent opens the
 * interest selector, because it owns the selector.
 */
module.exports = exports = defineComponent( {
	name: 'WelcomeBackDialog',
	components: { CdxButton, CdxDialog },
	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},
	emits: [ 'update:open', 'select-interests' ],
	setup( props, { emit } ) {
		const userName = mw.user.getName();
		return {
			wrappedOpen: useModelWrapper( toRef( props, 'open' ), emit, 'update:open' ),
			title: mw.message( 'growthexperiments-home-welcome-back-title', userName ).text(),
			subtitle: mw.msg( 'growthexperiments-home-welcome-back-text' ),
			selectInterestsLabel: mw.msg( 'growthexperiments-home-welcome-back-select-interests' ),
		};
	},
	mounted() {
		// The parent mounts the dialog closed for all other users. The dialog only
		// opens on mount, so an open dialog here is the one time it shows.
		// Save on show, not on close: the dialog must not show again when the
		// user leaves the page with the dialog open.
		if ( !this.open ) {
			return;
		}
		new mw.Api().saveOption( INTRO_SEEN_PREF, 1 ).catch( ( error ) => {
			mw.errorLogger.logError(
				new Error( 'Unable to save that the welcome back dialog was seen: ' + error ),
				'error.growthexperiments',
			);
		} );
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.growthexperiments-welcome-back-dialog {
	&__image {
		background: url( ../../images/bg_flashlight.gif ) transparent 50% / contain no-repeat;
		width: 100%;
		max-width: @size-1600;
		aspect-ratio: 320 / 308;
		// The image is narrower than the dialog body. Center it, as in Account Setup.
		margin: 0 auto;
	}

	// Add specificity to beat .cdx-button { max-width: 28rem; }
	.growthexperiments-welcome-back-dialog__select-interests {
		width: 100%;
		max-width: none;
	}
}
</style>
