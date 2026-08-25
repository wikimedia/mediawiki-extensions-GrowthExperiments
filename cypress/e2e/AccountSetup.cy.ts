describe( 'Account Setup', () => {
	it( 'shows new Account Setup if user is in treatment group', () => {
		cy.visit( 'index.php?title=JR-430_Mountaineer&mobileaction=toggle_view_mobile' );

		cy.window().should( 'have.property', 'mw' );
		cy.window().its( 'mw.tk' ).should( 'exist' );
		cy.window().then(
			async ( window: Cypress.AUTWindow & { mw: MediaWiki } ): Promise<void> => {
				// @ts-expect-error tk definitions are still missing: wikimedia/typescript-types#69
				window.mw.tk.overrideExperimentGroup( 'de-1-3-1-specialhomepage-onboarding-ab-test', 'treatment' );
			},
		);

		// open user menu
		cy.get( '#minerva-user-menu-checkbox' ).click();
		// click sign-up
		cy.get( '.minerva-user-menu a.menu__item--createaccount' ).should( 'be.visible' ).click();

		const username = 'cypress-testuser-' + Math.random();
		cy.get( '#wpName2' ).type( username );
		const password = 'cypress-password' + Math.random();
		cy.get( '#wpPassword2' ).type( password );
		cy.get( '#wpRetype' ).type( password );
		cy.get( '#wpCreateaccount' ).click();

		cy.get( '[data-test-id="account-setup-step-1-progress"]' ).click();
		cy.get( '[data-test-id="account-setup-step-2-both"]' ).click();

		cy.get( '.cdx-input-chip__text' ).should( 'have.text', 'JR-430 Mountaineer' );

		cy.get( '[data-test-id="account-setup-step-3-finish"]' ).click();

		cy.get( '#growthexperiments-homepage-module-suggested-edits' ).should( 'be.visible' );
	} );
} );
