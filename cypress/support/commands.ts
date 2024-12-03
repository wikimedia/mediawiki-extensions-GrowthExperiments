Cypress.Commands.add( 'loginViaApi', ( username: string, password: string ): void => {
	cy.visit( '/index.php' );
	cy.window().should( 'have.property', 'mw' );
	cy.window().its( 'mw.Api' ).should( 'exist' );
	cy.window().then(
		async ( window: Cypress.AUTWindow & { mw: MediaWiki } ): Promise<void> => {
			const api = new window.mw.Api() as
				MwApi & { login: ( u: string, p: string ) => JQuery.Promise<void> };
			await api.login( username, password );
		},
	);
} );

/* eslint-disable @typescript-eslint/no-namespace */
declare global {
	namespace Cypress {
		interface Chainable {
			loginViaApi( username: string, password: string ): Chainable<JQuery<HTMLElement>>;
		}
	}
}

export {};
