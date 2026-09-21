class SpecialHomepage {
	public get suggestedEditsCardTitle(): ReturnType<typeof cy.get> {
		return cy.get( '.se-card-title' );
	}

	public get suggestedEditsCardLink(): ReturnType<typeof cy.get> {
		return cy.get( 'a.se-card-content' );
	}

	public get suggestedEditsPreviousButton(): ReturnType<typeof cy.get> {
		return cy.get( '.suggested-edits-previous .oo-ui-buttonElement-button' );
	}

	public get suggestedEditsNextButton(): ReturnType<typeof cy.get> {
		return cy.get( '.suggested-edits-next .oo-ui-buttonElement-button' );
	}

	public get mentorshipLearnMoreLink(): ReturnType<typeof cy.get> {
		return cy.get( '#growthexperiments-homepage-mentorship-learn-more' );
	}

	public get mentorshipAboutDialog(): ReturnType<typeof cy.get> {
		return cy.get( '.growthexperiments-homepage-mentorship-about-mentorship' );
	}
}

export default SpecialHomepage;
