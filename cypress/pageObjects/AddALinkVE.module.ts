export default class AddALinkVEModule {

	public get linkInspector(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-recommendedLinkToolbarDialog' );
	}

	public get linkInspectorTargetTitle(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-recommendedLinkToolbarDialog-linkPreview-content' );
	}

	public get yesButton(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-recommendedLinkToolbarDialog-buttons-yes' );
	}

	public get noButton(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-recommendedLinkToolbarDialog-buttons-no' );
	}

	public get rejectionDialogDoneButton(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-recommendedLinkRejectionDialog .oo-ui-messageDialog-actions' );
	}

}
