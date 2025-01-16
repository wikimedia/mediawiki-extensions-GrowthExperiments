class KeepGoing {

	public get postEditDrawer(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-postEditDrawer', { timeout: 60000 } );
	}

	public get smallTaskCardLink(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-postEditDrawer a.mw-ge-small-task-card' );
	}
}

export default KeepGoing;
