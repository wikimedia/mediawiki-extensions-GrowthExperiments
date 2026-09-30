<template>
	<teleport :to="headerTarget">
		<module-header-menu
			v-slot="{ anchor }"
			:menu-items="menuItems"
			:footer-item="menuFooterItem"
			:button-label="menuButtonLabel"
			@select="openPanel = $event"
		>
			<module-panel
				:open="openPanel === 'status'"
				:anchor="anchor"
				:is-narrow="isNarrow"
				:title="statusTitle"
				@update:open="closeStatus"
			>
				<mentorship-status
					:action="statusAction"
					:mentor-gender="mentorGender"
					@busy="statusBusy = $event"
					@cancel="closePanel"
				>
				</mentorship-status>
			</module-panel>

			<module-panel
				:open="openPanel === 'about'"
				:anchor="anchor"
				:is-narrow="isNarrow"
				:title="aboutTitle"
				@update:open="closePanel"
			>
				<p>{{ aboutPar1 }}</p>
				<p>{{ aboutPar2 }}</p>
			</module-panel>
		</module-header-menu>
	</teleport>

	<question-dialog
		v-if="poster"
		v-model:open="questionOpen"
		v-bind="poster"
	>
	</question-dialog>
</template>

<script>
const { defineComponent, ref, onMounted, onUnmounted } = require( 'vue' );
// init.js loads this module before it requires this file.
const { ModuleHeaderMenu, ModulePanel } = require( 'ext.personalDashboard.common' );
const MentorshipStatus = require( './MentorshipStatus.vue' );
const QuestionDialog = require( './QuestionDialog.vue' );

// PersonalDashboard's own breakpoint, which its island loader reads the same way.
// The loader skips server-rendered modules, so this module cannot get isNarrow
// from it.
// FIXME: Use useViewport() when ext.personalDashboard.common exports it. It is
// in ext.personalDashboard.special now, which does not export its files.
function useIsNarrow() {
	const container = document.querySelector( '.personal-dashboard-container' );
	const breakpoint = ( container && parseInt(
		getComputedStyle( container ).getPropertyValue( '--personal-dashboard-mobile-breakpoint' ), 10,
	) ) || 639;
	const mql = window.matchMedia( '(max-width: ' + breakpoint + 'px)' );
	const isNarrow = ref( mql.matches );
	function onChange( event ) {
		isNarrow.value = event.matches;
	}
	onMounted( () => mql.addEventListener( 'change', onChange ) );
	onUnmounted( () => mql.removeEventListener( 'change', onChange ) );
	return isNarrow;
}

module.exports = exports = defineComponent( {
	name: 'MentorshipApp',
	components: { ModuleHeaderMenu, ModulePanel, MentorshipStatus, QuestionDialog },
	props: {
		// The id of the header menu slot that BaseModule emits.
		headerSlot: { type: String, required: true },
		optedOut: { type: Boolean, required: true },
		preintroDismissedPref: { type: String, required: true },
		// Null when there is no mentor to ask (the opted-out card).
		poster: { type: Object, default: null },
		mentorGender: { type: String, default: 'unknown' },
	},
	setup( props ) {
		const openPanel = ref( null );
		const questionOpen = ref( false );
		const statusAction = props.optedOut ? 'optin' : 'optout';
		// Closing mid-request would hide the result: a reload into the state the
		// user thought they cancelled, or an error nobody sees.
		const statusBusy = ref( false );
		const closePanel = () => {
			openPanel.value = null;
		};

		// The server-rendered buttons stay the entry points; this only takes over
		// their clicks. The ask link keeps its href as the no-JS fallback.
		const controls = [
			[ '.personal-dashboard-mentorship__ask', () => {
				questionOpen.value = true;
			} ],
			[ '.personal-dashboard-mentorship__optin', () => {
				openPanel.value = 'status';
			} ],
			[ '.personal-dashboard-mentorship__message-dismiss', ( button ) => {
				const message = button.closest( '.personal-dashboard-mentorship__message' );
				// The away notice comes back on each load: it is about now.
				if ( message.classList.contains( 'personal-dashboard-mentorship__preintro' ) ) {
					new mw.Api().saveOption( props.preintroDismissedPref, 1 );
				}
				message.remove();
			} ],
		];
		const listeners = [];
		onMounted( () => {
			for ( const [ selector, open ] of controls ) {
				const el = document.querySelector( selector );
				if ( el ) {
					const listener = ( e ) => {
						e.preventDefault();
						open( el );
					};
					el.addEventListener( 'click', listener );
					listeners.push( [ el, listener ] );
				}
			}
		} );
		onUnmounted( () => {
			for ( const [ el, listener ] of listeners ) {
				el.removeEventListener( 'click', listener );
			}
		} );

		return {
			headerTarget: '#' + CSS.escape( props.headerSlot ),
			isNarrow: useIsNarrow(),
			openPanel,
			questionOpen,
			statusAction,
			statusBusy,
			closePanel,
			closeStatus: () => {
				if ( !statusBusy.value ) {
					closePanel();
				}
			},
			menuItems: [ {
				value: 'status',
				label: mw.msg( props.optedOut ?
					'growthexperiments-homepage-mentorship-optin-button' :
					'growthexperiments-homepage-mentorship-ellipsis-menu-optout' ),
			} ],
			menuFooterItem: {
				value: 'about',
				label: mw.msg( 'growthexperiments-homepage-mentorship-ellipsis-menu-about' ),
			},
			menuButtonLabel: mw.msg( 'growthexperiments-homepage-mentorship-menu-button-label' ),
			statusTitle: mw.msg( props.optedOut ?
				'growthexperiments-homepage-mentorship-confirm-dialog-header' :
				'growthexperiments-homepage-mentorship-optout-header' ),
			aboutTitle: mw.msg( 'growthexperiments-homepage-mentorship-about-header' ),
			aboutPar1: mw.message(
				'growthexperiments-homepage-mentorship-about-mentor-par1', props.mentorGender,
			).text(),
			aboutPar2: mw.message(
				'growthexperiments-homepage-mentorship-about-mentor-par2', props.mentorGender,
			).text(),
		};
	},
} );
</script>
