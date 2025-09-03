import Homepage from '../pageObjects/SpecialHomepage.page';
import GuidedTour from '../pageObjects/GuidedTour.module';
import KeepGoingModule from '../pageObjects/KeepGoing.module';

const homepage = new Homepage();
const guidedTour = new GuidedTour();
const keepGoingModule = new KeepGoingModule();

describe( 'Revise Tone', () => {
	it( 'Shows the Revise Tone Edit Check and tags edits', () => {
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
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'revise-tone', 'image-recommendation' ] ),
		} );
		guidedTour.close( 'homepage_discovery' );

		cy.visit( 'index.php?title=Special:Homepage' );
		homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
		homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
		homepage.suggestedEditsCardLink.click();

		cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Revise' ).click();

		const peacockParagraphLength = 109;
		const deleteParagraph = '{backspace}'.repeat( peacockParagraphLength );
		cy.get( '#Tourism_and_Recreation + p' ).type( deleteParagraph + 'Kristallsee attracts approximately 25,000 visitors annually, primarily during the summer months from June to September.' );

		cy.get( '.ve-ui-toolbar-saveButton' ).should( 'be.visible' ).click();
		cy.get( '.ve-ui-mwSaveDialog .oo-ui-processDialog-actions-primary' ).should( 'be.visible' ).click();

		keepGoingModule.postEditDrawer.should( 'be.visible' );
		keepGoingModule.smallTaskCardLink.should( 'have.attr', 'href' );
		cy.assertTagsOfCurrentPageRevision( [ 'newcomer task', 'newcomer task revise tone' ] );
	} );
} );
