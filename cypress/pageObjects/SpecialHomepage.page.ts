class SpecialHomepage {
	public get suggestedEditsCardTitle(): ReturnType<typeof cy.get> {
		return cy.get( '.se-card-title' );
	}

	public get suggestedEditsPreviousButton(): ReturnType<typeof cy.get> {
		return cy.get( '.suggested-edits-previous .oo-ui-buttonElement-button' );
	}

	public get suggestedEditsNextButton(): ReturnType<typeof cy.get> {
		return cy.get( '.suggested-edits-next .oo-ui-buttonElement-button' );
	}
}

export default SpecialHomepage;
