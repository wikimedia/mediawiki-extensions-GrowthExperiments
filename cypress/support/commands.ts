Cypress.Commands.add( 'loginViaApi', ( username: string, password: string ): void => {
	cy.visit( '/index.php' );
	cy.window().should( 'have.property', 'mw' );
	cy.window().its( 'mw' ).should( 'have.property', 'Api' );
	cy.window().its( 'mw' ).then( async ( mw ): Promise<void> => {
		const api = new mw.Api();
		await api.login( username, password );
	} );
} );
