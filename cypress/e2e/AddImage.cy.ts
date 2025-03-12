import Homepage from '../pageObjects/SpecialHomepage.page';
import GuidedTour from '../pageObjects/GuidedTour.module';

const homepage = new Homepage();
const guidedTour = new GuidedTour();

describe( 'Add Image Structured Task', () => {

	it( 'desktop: user can view image info and image details', () => {
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			cy.loginViaApi( username, password );
		} );
		cy.setUserOptions( {
			'growthexperiments-tour-homepage-welcome': '1',
			'growthexperiments-addimage-onboarding': '1',
			'growthexperiments-addimage-caption-onboarding': '1',
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'image-recommendation' ] ),
		} );
		guidedTour.close( 'homepage_discovery' );

		cy.visit( 'index.php?title=Special:Homepage' );
		homepage.suggestedEditsCardTitle.should( 'have.text', 'Ma\'amoul' );
		homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
		homepage.suggestedEditsCardLink.click();

		cy.get( '.mw-ge-recommendedImageToolbarDialog' ).should( 'be.visible' );

		// view image info
		cy.get( '.mw-ge-recommendedImageToolbarDialog-details-button' ).click();
		cy.get( '.oo-ui-messageDialog-message' ).should( 'contain.text', 'File:Mamoul biscotti libanesi.jpg' );

		// close image info
		cy.get( '.oo-ui-messageDialog-actions .oo-ui-buttonElement-button' ).click();

		// accept suggestion
		cy.get( '.mw-ge-recommendedImageToolbarDialog-buttons-yes' ).click();

		// view image details
		cy.get( '.mw-ge-recommendedImage-detailsButton' ).click();
		cy.get( '.mw-ge-addImageDetailsDialog-fields' ).should( 'contain.text', 'File:Mamoul biscotti libanesi.jpg' );

		// close image details
		cy.get( '.oo-ui-messageDialog-actions .oo-ui-buttonElement-button' ).click();

		// switchToReadMode
		cy.get( '#ca-view' ).click();

		// discard edits
		cy.get( '.ve-ui-overlay-global .oo-ui-flaggedElement-destructive' ).click();
	} );

	it( 'mobile: user can close the image suggestion UI', () => {
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			cy.loginViaApi( username, password );
		} );
		cy.setUserOptions( {
			'growthexperiments-addimage-onboarding': '1',
			'growthexperiments-addimage-caption-onboarding': '1',
			'growthexperiments-tour-homepage-welcome': '1',
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'image-recommendation' ] ),
		} );
		cy.visit( 'index.php?title=Special:Homepage&mobileaction=toggle_view_mobile#/homepage/suggested-edits' );
		cy.get( '.se-card-title' ).should( 'have.text', 'Ma\'amoul' );
		homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
		homepage.suggestedEditsCardLink.click();

		cy.get( '.mw-ge-recommendedImageToolbarDialog', { timeout: 60000 } ).should( 'be.visible' );

		// close image inspector
		cy.get( '.oo-ui-tool-name-back' ).click();
	} );
} );
