const { createMwApp } = require( 'vue' );
const QuestionDialog = require( './QuestionDialog.vue' );
const MentorshipStatusDialog = require( './MentorshipStatusDialog.vue' );
const AboutMentorshipDialog = require( './AboutMentorshipDialog.vue' );
const state = require( './state.js' );

// The card is server-rendered, so the ask button already exists in the DOM and
// its href is the no-JS fallback (the talk page new-section editor). Here we
// upgrade the click into a Codex dialog, teleported to the end of the body.
const posterConfig = mw.config.get( 'wgGrowthExperimentsMentorshipPoster' );
const askButton = document.querySelector( '.personal-dashboard-mentorship__ask' );

if ( posterConfig && askButton ) {
	const mount = document.createElement( 'div' );
	document.body.appendChild( mount );
	createMwApp( QuestionDialog, posterConfig ).mount( mount );

	askButton.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		state.open = true;
	} );
}

// The opt-out control (on a mentored card) and the opt-in control (on the
// opted-out card) are mutually exclusive and share one confirm -> set state ->
// reload dialog; whichever the server rendered decides the action it drives.
const optOut = document.querySelector( '.personal-dashboard-mentorship__optout' );
const optIn = document.querySelector( '.personal-dashboard-mentorship__optin' );
const statusControl = optOut || optIn;

if ( statusControl ) {
	const mount = document.createElement( 'div' );
	document.body.appendChild( mount );
	createMwApp( MentorshipStatusDialog, { action: optOut ? 'optout' : 'optin' } ).mount( mount );

	statusControl.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		state.confirm = true;
	} );
}

// The about-mentorship control only exists on the mentored card, alongside
// the opt-out control, since the dialog's copy has nothing to say without a
// mentor to describe.
const aboutControl = document.querySelector( '.personal-dashboard-mentorship__about' );

if ( aboutControl ) {
	const mount = document.createElement( 'div' );
	document.body.appendChild( mount );
	createMwApp( AboutMentorshipDialog ).mount( mount );

	aboutControl.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		state.about = true;
	} );
}
