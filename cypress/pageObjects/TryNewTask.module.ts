class TryNewTask {

	public get postEditDrawer(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-postEditDrawer-tryNewTask', { timeout: 60000 } );
	}

	public get secondaryAction(): ReturnType<typeof cy.get> {
		return cy.get( '.mw-ge-postEditDrawer .mw-ge-help-panel-postedit-footer-nothanks a' );
	}
}

export default TryNewTask;
