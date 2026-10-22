import SpecialHome from '../pageObjects/SpecialHome.page';

const home = new SpecialHome();

const SUGGESTED_EDITS_MODULE = 'ext.growthExperiments.PersonalDashboard.SuggestedEdits';

function signUp(): void {
	const username = 'cypress-testuser-' + Math.random();
	cy.get( '#wpName2' ).type( username );
	const password = 'cypress-password' + Math.random();
	cy.get( '#wpPassword2' ).type( password );
	cy.get( '#wpRetype' ).type( password );
	cy.get( '#wpCreateaccount' ).click();
}

describe( 'Account Setup on a wiki with Home', () => {
	it( 'shows Account Setup on its own page after signup and then goes to Home', () => {
		cy.visit( 'index.php?title=JR-430_Mountaineer' );
		cy.get( '#pt-createaccount-2' ).click();
		signUp();

		cy.location( 'href' ).should( 'contain', 'Special:AccountSetup' );
		cy.get( '#welcome-survey-form' ).should( 'not.exist' );
		cy.get( '[data-test-id="account-setup-step-1-progress"]' ).click();
		cy.get( '[data-test-id="account-setup-step-2-both"]' ).click();

		// The signup article is the first interest.
		cy.get( '.cdx-input-chip__text' ).should( 'have.text', 'JR-430 Mountaineer' );

		cy.get( '[data-test-id="account-setup-step-3-finish"]' ).click();

		cy.location( 'href' ).should( 'contain', 'Special:Home' );
		home.module( SUGGESTED_EDITS_MODULE ).should( 'be.visible' );

		// The user finished the setup, so the page sends them to Home.
		cy.visit( 'index.php?title=Special:AccountSetup' );
		cy.location( 'href' ).should( 'contain', 'Special:Home' );
		cy.get( '#growthexperiments-account_setup' ).should( 'not.exist' );

		// NOTE: a job sets the "intro seen" preference. Jobs run after an undefined
		//       time during the tests, so do not assert on it here.
	} );

	it( 'goes to Home when the user skips Account Setup', () => {
		cy.visit( 'index.php?title=JR-430_Mountaineer' );
		cy.get( '#pt-createaccount-2' ).click();
		signUp();

		cy.location( 'href' ).should( 'contain', 'Special:AccountSetup' );
		cy.get( '.ext-growthExperiments-account-setup-header button[aria-label="Skip"]' ).click();

		cy.location( 'href' ).should( 'contain', 'Special:Home' );
		home.module( SUGGESTED_EDITS_MODULE ).should( 'be.visible' );
	} );

	it( 'shows Account Setup on mobile', () => {
		cy.viewport( 360, 780 );
		cy.visit( 'index.php?title=JR-430_Mountaineer&mobileaction=toggle_view_mobile' );
		cy.get( '#minerva-user-menu-checkbox' ).click();
		cy.get( '.minerva-user-menu a.menu__item--createaccount' ).should( 'be.visible' ).click();
		signUp();

		cy.location( 'href' ).should( 'contain', 'Special:AccountSetup' );
		cy.get( '[data-test-id="account-setup-step-1-progress"]' ).click();
		cy.get( '[data-test-id="account-setup-step-2-reading"]' ).click();
		cy.get( '[data-test-id="account-setup-step-3-finish"]' ).click();

		cy.location( 'href' ).should( 'contain', 'Special:Home' );
		home.module( SUGGESTED_EDITS_MODULE ).should( 'be.visible' );
	} );

	it( 'returns a mid-edit signup to the editor and does not show Account Setup', () => {
		cy.visit(
			'index.php?title=Special:CreateAccount&returnto=JR-430_Mountaineer&returntoquery=action%3Dedit',
		);
		signUp();

		// Wait until the signup is complete.
		cy.window().its( 'mw.config' ).invoke( 'get', 'wgUserName' )
			.should( 'match', /^Cypress-testuser-/ );
		cy.location( 'href' ).then( ( href ) => {
			if ( href.includes( 'Special:CreateAccount' ) ) {
				// Without CentralAuth, core does not redirect after a signup.
				// It shows a welcome page with a link back to the editor.
				cy.get( '#mw-returnto a' ).should( 'have.attr', 'href' ).and( 'contain', 'action=edit' );
			} else {
				// With CentralAuth, the user goes back to the editor.
				expect( href ).to.contain( 'action=edit' );
			}
		} );
		cy.get( '#growthexperiments-account_setup' ).should( 'not.exist' );
	} );
} );
