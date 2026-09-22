describe( 'Account Setup', () => {
	it( 'shows new Account Setup if user is in treatment group on mobile', () => {
		cy.viewport( 360, 780 );
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

		cy.get( '#growthexperiments-homepage-module-suggested-edits' ).should( 'exist' ).scrollIntoView();
		cy.get( '#growthexperiments-homepage-module-suggested-edits' ).should( 'be.visible' );
		cy.get( '.homepage-welcome-notice' ).should( 'not.exist' );
		cy.get( '.mw-ge-homepage-discovery-banner-mobile' ).should( 'not.exist' );
		cy.get( '.growthexperiments-homepage-module-mobile-summary' ).then( ( elements ) => {
			const idsOfElementsOnHomepage = elements.toArray().map( ( element ) => element.id );
			const expectedElementIds = [
				'growthexperiments-homepage-module-startemail',
				'growthexperiments-homepage-module-suggested-edits',
				'growthexperiments-homepage-module-reading-recommendations',
				'growthexperiments-homepage-module-impact',
				'growthexperiments-homepage-module-help',
			];
			expect( idsOfElementsOnHomepage ).to.have.ordered.members( expectedElementIds );
		} );

		cy.visit( 'index.php?title=JR-430_Mountaineer' );
		cy.get( '.mw-ge-homepage-discovery-banner-mobile' ).should( 'be.visible' );
	} );

	it( 'shows new Account Setup if user is in treatment group on desktop', () => {
		cy.visit( 'index.php?title=JR-430_Mountaineer' );

		cy.window().should( 'have.property', 'mw' );
		cy.window().its( 'mw.tk' ).should( 'exist' );
		cy.window().then(
			async ( window: Cypress.AUTWindow & { mw: MediaWiki } ): Promise<void> => {
				// @ts-expect-error tk definitions are still missing: wikimedia/typescript-types#69
				window.mw.tk.overrideExperimentGroup( 'de-1-3-1-specialhomepage-onboarding-ab-test', 'treatment' );
			},
		);

		// click create account link
		cy.get( '#pt-createaccount-2' ).click();

		const username = 'cypress-testuser-' + Math.random();
		cy.get( '#wpName2' ).type( username );
		const password = 'cypress-password' + Math.random();
		cy.get( '#wpPassword2' ).type( password );
		cy.get( '#wpRetype' ).type( password );
		cy.get( '#wpCreateaccount' ).click();

		cy.get( '[data-test-id="account-setup-step-1-progress"]' ).click();
		cy.get( '[data-test-id="account-setup-step-2-reading"]' ).click();

		cy.get( '.cdx-input-chip__text' ).should( 'have.text', 'JR-430 Mountaineer' );

		cy.get( '[data-test-id="account-setup-step-3-finish"]' ).click();

		cy.get( '#growthexperiments-homepage-module-suggested-edits' ).should( 'exist' ).scrollIntoView();
		cy.get( '#growthexperiments-homepage-module-suggested-edits' ).should( 'be.visible' );
		cy.get( '.homepage-welcome-notice' ).should( 'not.exist' );
		cy.get( '.growthexperiments-homepage-module-desktop' ).then( ( elements ) => {
			const idsOfElementsOnHomepage = elements.toArray().map( ( element ) => element.id );
			const expectedElementIds = [
				'growthexperiments-homepage-module-startemail',
				'growthexperiments-homepage-module-reading-recommendations',
				'growthexperiments-homepage-module-suggested-edits',
				'growthexperiments-homepage-module-impact',
				'growthexperiments-homepage-module-help',
			];
			expect( idsOfElementsOnHomepage ).to.have.ordered.members( expectedElementIds );
		} );

		// NOTE: the guided tour after redirect is not testable because the respective useroption is set in a job,
		//       and during tests that takes an undefined amount of time to run and so would result in flakyness
	} );
} );
