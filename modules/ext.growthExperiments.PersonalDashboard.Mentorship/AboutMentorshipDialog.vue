<template>
	<cdx-dialog
		v-model:open="state.about"
		:title="title"
		:default-action="defaultAction"
		@default="state.about = false"
	>
		<h4>{{ subheaderMentor }}</h4>
		<p>{{ mentorPar1 }}</p>
		<p>{{ mentorPar2 }}</p>
		<h4>{{ subheaderOptout }}</h4>
		<p>{{ optoutPar1 }}</p>
		<cdx-button action="destructive" @click="onOptOut">
			{{ optoutButtonLabel }}
		</cdx-button>
	</cdx-dialog>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxDialog } = require( './codex.js' );
const state = require( './state.js' );

// Ported from the Homepage module's "about mentors" ellipsis-menu modal,
// reusing its copy and the embedded opt-out button re-triggers the same
// confirm -> set state -> reload flow the footer opt-out control drives.
module.exports = defineComponent( {
	name: 'AboutMentorshipDialog',
	components: { CdxButton, CdxDialog },
	setup() {
		const mentorGender = mw.config.get( 'wgGrowthExperimentsMentorshipMentorGender' );

		function onOptOut() {
			state.about = false;
			state.confirm = true;
		}

		return {
			state,
			title: mw.msg( 'growthexperiments-homepage-mentorship-about-header' ),
			subheaderMentor: mw.message(
				'growthexperiments-homepage-mentorship-about-subheader-mentor', mentorGender,
			).text(),
			mentorPar1: mw.message(
				'growthexperiments-homepage-mentorship-about-mentor-par1', mentorGender,
			).text(),
			mentorPar2: mw.message(
				'growthexperiments-homepage-mentorship-about-mentor-par2', mentorGender,
			).text(),
			subheaderOptout: mw.msg( 'growthexperiments-homepage-mentorship-about-subheader-optout' ),
			optoutPar1: mw.msg( 'growthexperiments-homepage-mentorship-about-optout-par1' ),
			optoutButtonLabel: mw.msg( 'growthexperiments-homepage-mentorship-ellipsis-menu-optout' ),
			defaultAction: {
				label: mw.msg( 'growthexperiments-homepage-mentorship-about-done' ),
			},
			onOptOut,
		};
	},
} );
</script>
