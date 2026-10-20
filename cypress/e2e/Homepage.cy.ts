import Homepage from '../pageObjects/SpecialHomepage.page';
import GuidedTour from '../pageObjects/GuidedTour.module';
import SpecialHome from '../pageObjects/SpecialHome.page';

const homepage = new Homepage();
const guidedTour = new GuidedTour();
const home = new SpecialHome();

const SUGGESTED_EDITS_MODULE = 'ext.growthExperiments.PersonalDashboard.SuggestedEdits';

describe( 'Special:Homepage', () => {

	it( 'shows the normal experience and modules for users not in an experiment', () => {
		// The Welcome Survey shows only on wikis without Home.
		// See GrowthExperiments.LocalSettings.php.
		cy.setCookie( 'GECypressNoHome', '1' );
		cy.viewport( 360, 780 );
		cy.visit( 'index.php?title=JR-430_Mountaineer&mobileaction=toggle_view_mobile' );

		cy.window().should( 'have.property', 'mw' );
		cy.window().its( 'mw.tk' ).should( 'exist' );
		cy.window().then(
			async ( window: Cypress.AUTWindow & { mw: MediaWiki } ): Promise<void> => {
				// @ts-expect-error tk definitions are still missing: wikimedia/typescript-types#69
				window.mw.tk.clearExperimentOverrides();
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

		cy.get( '#welcome-survey-form' ).should( 'be.visible' );
		cy.get( '#welcome-survey-form button[name="save"]' ).scrollIntoView();
		cy.get( '#welcome-survey-form button[name="save"]' ).click();
		cy.contains( 'Go to your homepage' ).click();

		cy.get( '.ext-growthExperiments-account-setup-step-1' ).should( 'not.exist' );
		cy.get( '.homepage-welcome-notice' ).should( 'be.visible' );
		cy.get( '.homepage-welcome-notice a' ).click();

		cy.get( '.mw-ge-homepage-discovery-banner-mobile' ).should( 'be.visible' );
		cy.get( '.mw-ge-homepage-discovery-banner-close' ).click();

		cy.get( '.growthexperiments-homepage-module-mobile-summary' ).then( ( elements ) => {
			const idsOfElementsOnHomepage = elements.toArray().map( ( element ) => element.id );
			const expectedElementIds = [
				'growthexperiments-homepage-module-startemail',
				'growthexperiments-homepage-module-suggested-edits',
				'growthexperiments-homepage-module-impact',
				'growthexperiments-homepage-module-help',
			];
			expect( idsOfElementsOnHomepage ).to.deep.equal( expectedElementIds );
		} );
	} );

	// T392940
	it.skip( 'Shows a suggested edits card and allows navigation forwards and backwards through queue', () => {
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			cy.loginViaApi( username, password );
		} );
		cy.setUserOptions( {
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'copyedit' ] ),
		} );
		guidedTour.close( 'homepage_discovery' );

		cy.visit( 'index.php?title=Special:Homepage' );
		guidedTour.close( 'homepage_welcome' );

		homepage.suggestedEditsCardTitle.should( 'have.text', 'Classical kemençe' );
		homepage.suggestedEditsPreviousButton.should( 'have.attr', 'aria-disabled', 'true' );
		homepage.suggestedEditsNextButton.should( 'not.have.attr', 'aria-disabled' );
		homepage.suggestedEditsNextButton.click();
		homepage.suggestedEditsCardTitle.should( 'have.text', 'Cretan lyra' );
		homepage.suggestedEditsPreviousButton.click();
		homepage.suggestedEditsPreviousButton.should( 'have.attr', 'aria-disabled', 'true' );

		// Go to the end of queue card.
		homepage.suggestedEditsNextButton.click();
		homepage.suggestedEditsNextButton.click();
		homepage.suggestedEditsCardTitle.should( 'have.text', 'No more suggestions' );
		homepage.suggestedEditsNextButton.should( 'have.attr', 'aria-disabled', 'true' );
	} );

	// The test LocalSettings turn on Home, so Special:Homepage redirects to Special:Home.
	describe( 'on a wiki with Home', () => {

		it( 'redirects to Special:Home', () => {
			cy.loginAsUser( 'GE-HomepageRedirect' );
			cy.visit( 'index.php?title=Special:Homepage' );

			cy.location( 'href' ).should( 'contain', 'Special:Home' )
				.and( 'not.contain', 'Special:Homepage' );
			home.module( SUGGESTED_EDITS_MODULE ).should( 'be.visible' );
		} );

		it( 'sends an anonymous visitor to the login form for Special:Home', () => {
			// Cypress clears the cookies between the tests, so this visit is anonymous.
			cy.visit( 'index.php?title=Special:Homepage' );

			cy.get( '#wpName1' ).should( 'be.visible' );
			cy.location( 'href' ).should( 'contain', 'returnto=Special%3AHome' );
		} );

		it( 'opens the task article from a Home task card', () => {
			cy.loginAsUser( 'GE-HomepageRedirect' );
			cy.visit( 'index.php?title=Special:Home' );

			// The task cards on Home still link to Special:Homepage/newcomertask (T439433).
			// That subpage does not redirect to Home.
			// A card is the CdxCard link itself, or it holds an overlay link. Accept both,
			// so that PersonalDashboard can change the card link without failing this test.
			home.suggestedEditsCards.first()
				.then( ( $card ) => (
					$card.is( 'a' ) ? $card : $card.find( '.personal-dashboard-feed__card__link' )
				) )
				.should( 'have.attr', 'href' ).and( 'contain', 'newcomertask' )
				.then( ( href ) => {
					cy.visit( String( href ) );
				} );

			cy.location( 'href' ).should( 'not.contain', 'Special:Home' );
			cy.location( 'search' ).should( 'contain', 'gesuggestededit=1' );
		} );
	} );
} );
