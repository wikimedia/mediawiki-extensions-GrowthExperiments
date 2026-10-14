import SpecialHome from '../pageObjects/SpecialHome.page';

const home = new SpecialHome();

// The modules of the `home` group this page renders. The names are also the
// routes the dashboard app pushes, so they show up in the URL.
const SUGGESTED_EDITS_MODULE = 'ext.growthExperiments.PersonalDashboard.SuggestedEdits';
const IMPACT_MODULE = 'ext.growthExperiments.impact';

describe( 'Special:Home', () => {

	it( 'greets the user and renders the home module group', () => {
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'GE-Home' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			// The impact module counts edits. Make one, so the card shows a number
			// that only this user can produce.
			cy.task( 'MwApi:Edit', {
				username: username,
				title: 'Testpage_' + username,
				text: 'Hello, this is a test message: ' + Math.random(),
				summary: 'GrowthExperiments Cypress browser test edit',
			} );
			cy.loginViaApi( username, password );

			cy.visit( 'index.php?title=Special:Home' );

			// The h1 greets the user by name, unlike the static label of the page
			// in Special:SpecialPages.
			home.title.should( 'contain', username );

			// Both cards come from the server, and both bodies come from an island
			// the dashboard app teleports in. Assert on the body, so the test fails
			// when the island does not mount.
			home.module( SUGGESTED_EDITS_MODULE ).should( 'be.visible' );
			// The card of the grid is always a summary, on every viewport.
			home.suggestedEditsCards.should( 'have.length', 3 );

			home.module( IMPACT_MODULE ).should( 'be.visible' );
			home.impactTotalEdits.should( 'have.text', '1' );
		} );
	} );

	it( 'opens the full list of suggestions from the card footer', () => {
		cy.loginAsUser( 'GE-Home' );
		cy.visit( 'index.php?title=Special:Home' );

		home.suggestedEditsSeeAllButton.click();

		// The module leaves its card and becomes the whole view. It keeps the one
		// instance, so there is still a single set of cards, now the full list.
		home.focusedView.should( 'be.visible' );
		home.suggestedEditsCards.should( 'have.length.greaterThan', 3 );
		// The route of the module is in the URL, so the view survives a reload and
		// can be linked to.
		cy.location( 'href' ).should( 'contain', SUGGESTED_EDITS_MODULE );
	} );

	it( 'tells a user without the newcomer homepage preference how to enable Home', () => {
		// Special:Home needs the newcomer homepage preference, which account
		// creation turns on. Turn it off again.
		//
		// Make a user for this. cy.loginAsUser caches a session across specs, and
		// a cached user without the preference breaks every spec that reuses it.
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'GE-HomeNoPref' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			cy.loginViaApi( username, password );
		} );

		// Not cy.setUserOptions: that helper resets every option it knows about,
		// which keeps the sessions cy.loginAsUser caches across specs clean. This
		// user is made here and used once, so save the one preference the test
		// needs and leave the rest alone.
		cy.visit( '/index.php' );
		cy.window().its( 'mw.Api' ).should( 'exist' );
		cy.window().then(
			async ( { mw }: Cypress.AUTWindow & { mw: MediaWiki } ): Promise<void> => {
				await new mw.Api().saveOptions( { 'growthexperiments-homepage-enable': '0' } );
			},
		);

		cy.visit( 'index.php?title=Special:Home' );

		cy.get( '#firstHeading' ).should( 'contain', 'Home' );
		cy.get( '#mw-content-text' ).should( 'contain', 'To enable Home' );
		// The error page is only useful if it leads to the preference.
		cy.get( '#mw-content-text a[href*="Special:Preferences"]' ).should( 'be.visible' );
		home.module( SUGGESTED_EDITS_MODULE ).should( 'not.exist' );
	} );

	it( 'sends an anonymous visitor to the login form', () => {
		// Cypress clears the cookies between the tests, so this visit is anonymous.
		cy.visit( 'index.php?title=Special:Home' );

		cy.get( '#wpName1' ).should( 'be.visible' );
		// The login brings the visitor back to Home.
		cy.location( 'href' ).should( 'contain', 'returnto=Special%3AHome' );
	} );
} );
