import Homepage from '../pageObjects/SpecialHomepage.page';
import GuidedTour from '../pageObjects/GuidedTour.module';

const homepage = new Homepage();
const guidedTour = new GuidedTour();

describe( 'Special:Homepage', () => {
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
} );
