function login( username: string, password: string ): void {
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
}

Cypress.Commands.add( 'loginViaApi', ( username: string, password: string ): void => {
	login( username, password );
} );

Cypress.Commands.add( 'loginAsAdmin', (): void => {
	const config = Cypress.env();
	login(
		config.mediawikiAdminUsername,
		config.mediawikiAdminPassword,
	);
} );

Cypress.Commands.add( 'logout', (): void => {
	cy.visit( 'index.php?title=Special:UserLogout' );
	cy.get( '#mw-content-text button' ).click();
} );

Cypress.Commands.add( 'saveCommunityConfigurationForm', ( editSummary: string ): void => {
	cy.get( 'button' ).contains( 'Save changes' ).should( 'not.be.disabled' ).click();

	cy.get( '.cdx-dialog[role="dialog"]' ).should( 'be.visible' );

	cy.get( '.cdx-dialog__header__title' ).should( 'contain', 'Save changes' );

	cy.get( '[data-testid="edit-summary-text-area"]' )
		.should( 'have.attr', 'placeholder', 'Describe the changes that were made in the configuration' )
		.type( editSummary + '; ' + Cypress.currentTest.titlePath.join( ': ' ) );

	cy.get( '.cdx-dialog__footer__actions .cdx-button--action-progressive' )
		.contains( 'Save changes' )
		.click();

	cy.get( '.cdx-dialog[role="dialog"]' ).should( 'not.exist' );

	cy.contains( 'Your changes were saved' ).should( 'be.visible' );
} );

Cypress.Commands.add( 'setUserOptions', ( options ): void => {
	cy.visit( '/index.php' );
	cy.window().its( 'mw.Api' ).should( 'exist' );
	cy.window().then( async ( { mw }: Cypress.AUTWindow & { mw: MediaWiki } ): Promise<void> => {
		const api = new mw.Api();
		await api.saveOptions( options );
	} );
} );

Cypress.Commands.add( 'assertTagsOfCurrentPageRevision', ( expectedTags: string[] ): void => {
	cy.window().its( 'mw.config' ).invoke( 'get', 'wgCurRevisionId' ).then( ( revId ) => {
		cy.request( {
			method: 'GET',
			url: 'api.php',
			qs: {
				action: 'query',
				prop: 'revisions',
				rvprop: 'tags',
				revids: revId,
				format: 'json',
			},
		} ).then( ( response ) => {

			interface PageWithRevisionInfoWithTags {
				revisions: { tags: string[] }[];
			}

			const firstPage = Object.values(
				response.body.query.pages,
			)[ 0 ] as PageWithRevisionInfoWithTags;

			const tags = firstPage.revisions[ 0 ].tags;
			expect( tags ).to.include.members( expectedTags );
		} );
	} );
} );

/* eslint-disable @typescript-eslint/no-namespace */
declare global {
	namespace Cypress {
		interface Chainable {
			loginViaApi( username: string, password: string ): Chainable<JQuery<HTMLElement>>;
			loginAsAdmin(): Chainable<JQuery<HTMLElement>>;
			logout(): Chainable<JQuery<HTMLElement>>;
			saveCommunityConfigurationForm( editSummary: string ): Chainable<JQuery<HTMLElement>>;
			setUserOptions( options: Record<string, string> ): Chainable<JQuery<HTMLElement>>;
			assertTagsOfCurrentPageRevision(
				expectedTags: string[],
			): Chainable<JQuery<HTMLElement>>;
		}
	}
}

export {};
